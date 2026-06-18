<?php
namespace App\Models;

/**
 * AKABBO SOCIAL FUND
 * Loan Model
 *
 * Manages the complete loan lifecycle from application through
 * repayment, including schedule generation and performance tracking.
 */
class Loan extends BaseModel
{
    protected string $table = 'loans';
    protected bool $useSoftDelete = true;

    /**
     * Generate the next sequential loan number atomically.
     * Caller MUST wrap this in a transaction.
     */
    public function generateLoanNo(): string
    {
        return \App\Helpers\LoanSequence::nextFormatted();
    }

    /**
     * Calculate loan financials with optional shareholder privileges.
     *
     * @param float  $principal     Principal amount
     * @param float  $baseRate      Annual interest rate as percentage
     * @param int    $termMonths    Loan term in months
     * @param string $interestType  flat | reducing_balance | compound
     * @param bool   $isShareholder Is the member a shareholder?
     * @param int    $sharesHeld    Number of shares held (for tiered discounts)
     * @return array Calculated financial figures
     */
    public function calculateLoan(
        float $principal, 
        float $baseRate, 
        int $termMonths, 
        string $interestType,
        bool $isShareholder = false,
        int $sharesHeld = 0
    ): array {
        $rate = $baseRate;
        
        // Apply shareholder privileges if applicable
        if ($isShareholder) {
            $config = $this->db->fetchOne("SELECT loan_rate_discount FROM share_config ORDER BY id DESC LIMIT 1");
            $discount = (float)($config['loan_rate_discount'] ?? 0);
            $rate = max(0, $rate - $discount);
            
            // Additional 1% discount for major shareholders (>= 100 shares)
            if ($sharesHeld >= 100) {
                $rate = max(0, $rate - 1.00);
            }
            $rate = round($rate, 2);
        }

        $monthlyRate = $rate / 100 / 12;

        switch ($interestType) {
            case 'flat':
                $totalInterest      = $principal * ($rate / 100) * ($termMonths / 12);
                $totalPayable       = $principal + $totalInterest;
                $monthlyInstallment = $totalPayable / $termMonths;
                break;

            case 'reducing_balance':
                if ($monthlyRate == 0) {
                    $monthlyInstallment = $principal / $termMonths;
                } else {
                    $factor = pow(1 + $monthlyRate, $termMonths);
                    $monthlyInstallment = $principal * ($monthlyRate * $factor) / ($factor - 1);
                }
                $totalPayable   = $monthlyInstallment * $termMonths;
                $totalInterest  = $totalPayable - $principal;
                break;

            case 'compound':
                $totalPayable       = $principal * pow(1 + $monthlyRate, $termMonths);
                $totalInterest      = $totalPayable - $principal;
                $monthlyInstallment = $totalPayable / $termMonths;
                break;

            default:
                throw new \InvalidArgumentException("Unknown interest type: {$interestType}");
        }

        return [
            'principal'           => round($principal, 2),
            'total_interest'      => round($totalInterest, 2),
            'total_payable'       => round($totalPayable, 2),
            'monthly_installment' => round($monthlyInstallment, 2),
            'term_months'         => $termMonths,
            'interest_rate'       => $rate, // Returns adjusted rate if shareholder
            'interest_type'       => $interestType,
        ];
    }

    public function generateRepaymentSchedule(
        int $loanId,
        float $principal,
        float $rate,
        int $termMonths,
        string $interestType,
        string $firstRepayDate
    ): void {
        $this->db->execute("DELETE FROM loan_repayment_schedules WHERE loan_id = ?", [$loanId]);

        $monthlyRate    = $rate / 100 / 12;
        $balance        = $principal;
        $currentDate    = new \DateTime($firstRepayDate);
        $calc           = $this->calculateLoan($principal, $rate, $termMonths, $interestType);
        $monthlyPayment = $calc['monthly_installment'];

        for ($i = 1; $i <= $termMonths; $i++) {
            switch ($interestType) {
                case 'flat':
                    $interestDue  = ($principal * ($rate / 100)) / 12;
                    $principalDue = ($principal / $termMonths);
                    break;
                case 'reducing_balance':
                    $interestDue  = $balance * $monthlyRate;
                    $principalDue = $monthlyPayment - $interestDue;
                    if ($i === $termMonths) {
                        $principalDue = $balance;
                    }
                    break;
                case 'compound':
                    $interestDue  = $balance * $monthlyRate;
                    $principalDue = $monthlyPayment - $interestDue;
                    break;
                default:
                    $interestDue  = 0;
                    $principalDue = $monthlyPayment;
            }

            $principalDue = round($principalDue, 2);
            $interestDue  = round($interestDue, 2);
            $totalDue     = $principalDue + $interestDue;
            $balance      -= $principalDue;

            $this->db->execute("
                INSERT INTO loan_repayment_schedules
                    (loan_id, installment_no, due_date, principal_due, interest_due, total_due, status)
                VALUES (?, ?, ?, ?, ?, ?, 'upcoming')
            ", [$loanId, $i, $currentDate->format('Y-m-d'), $principalDue, $interestDue, $totalDue]);

            $currentDate->modify('+1 month');
        }
    }

    public function getDetail(int $loanId): ?array
    {
        $loan = $this->db->fetchOne("
            SELECT l.*,
                   CONCAT(m.first_name,' ',m.last_name) AS member_name,
                   m.member_no, m.phone AS member_phone,
                   lp.name AS product_name, lp.code AS product_code,
                   CONCAT(u.first_name,' ',u.last_name) AS created_by_name,
                   CONCAT(ua.first_name,' ',ua.last_name) AS approved_by_name
            FROM loans l
            JOIN members m ON m.id = l.member_id
            JOIN loan_products lp ON lp.id = l.loan_product_id
            JOIN users u ON u.id = l.created_by
            LEFT JOIN users ua ON ua.id = l.approved_by
            WHERE l.id = ? AND l.deleted_at IS NULL
        ", [$loanId]);

        if (!$loan) return null;

        $loan['schedule']   = $this->getSchedule($loanId);
        $loan['guarantors'] = $this->getGuarantors($loanId);
        return $loan;
        }

    public function getSchedule(int $loanId): array
    {
        return $this->db->fetchAll("SELECT * FROM loan_repayment_schedules WHERE loan_id = ? ORDER BY installment_no ASC", [$loanId]);
    }

    public function getGuarantors(int $loanId): array
    // validate guarantor audit COMPLIANCE_AUDIT.md (5. Loan Guarantor Management) show message if not eligible and reject guarantor
    {
        return $this->db->fetchAll("
            SELECT lg.*, CONCAT(m.first_name,' ',m.last_name) AS guarantor_name, m.phone, m.member_no
            FROM loan_guarantors lg
            JOIN members m ON m.id = lg.guarantor_member_id
            WHERE lg.loan_id = ?
        ", [$loanId]);
    }

    public function getList(int $page = 1, int $limit = 25, array $filters = []): array
    {
        $where  = ['l.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) { $where[] = 'l.status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['member_id'])) { $where[] = 'l.member_id = ?'; $params[] = $filters['member_id']; }
        if (!empty($filters['product_id'])) { $where[] = 'l.loan_product_id = ?'; $params[] = $filters['product_id']; }
        if (!empty($filters['search'])) {
            $like = '%' . $filters['search'] . '%';
            $where[] = "(l.loan_no LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ? OR m.member_no LIKE ?)";
            $params = array_merge($params, [$like, $like, $like]);
        }
        if (!empty($filters['date_from'])) { $where[] = 'l.application_date >= ?'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to'])) { $where[] = 'l.application_date <= ?'; $params[] = $filters['date_to']; }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $offset   = ($page - 1) * $limit;

        $total = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM loans l JOIN members m ON m.id = l.member_id {$whereSql}", $params);
        $data = $this->db->fetchAll("
            SELECT l.id, l.loan_no, l.principal_amount, l.total_payable, l.amount_paid,
                   l.balance_outstanding, l.term_months, l.interest_rate, l.status,
                   l.application_date, l.disbursement_date, l.expected_maturity_date,
                   CONCAT(m.first_name,' ',m.last_name) AS member_name, m.member_no, m.phone,
                   lp.name AS product_name
            FROM loans l
            JOIN members m ON m.id = l.member_id
            JOIN loan_products lp ON lp.id = l.loan_product_id
            {$whereSql}
            ORDER BY l.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ", $params);

        return ['data' => $data, 'total' => $total, 'page' => $page, 'per_page' => $limit, 'last_page' => (int)ceil($total / $limit)];
    }

    public function getStats(): array
    {
        return $this->db->fetchOne("
            SELECT
                COUNT(*)                                    AS total_loans,
                SUM(status = 'active')                      AS active_loans,
                SUM(status = 'pending')                     AS pending_loans,
                SUM(status = 'completed')                   AS completed_loans,
                SUM(status = 'defaulted')                   AS defaulted_loans,
                SUM(status = 'disbursed')                   AS disbursed_loans,
                COALESCE(SUM(principal_amount), 0)          AS total_principal,
                COALESCE(SUM(CASE WHEN status IN ('active','disbursed') THEN balance_outstanding ELSE 0 END), 0) AS outstanding_balance,
                COALESCE(SUM(amount_paid), 0)               AS total_repaid
            FROM loans WHERE deleted_at IS NULL
        ");
    }

    public function markOverdueInstallments(): int
    {
        return $this->db->execute("
            UPDATE loan_repayment_schedules
            SET status = 'overdue'
            WHERE due_date < CURDATE()
              AND status IN ('upcoming', 'due')
              AND total_paid < total_due
        ");
    }
}