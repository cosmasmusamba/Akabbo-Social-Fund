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
     * Generate the next sequential loan number (e.g. LN-2025-00123).
     */
    public function generateLoanNo(): string
    {
        $year = date('Y');
        $last = $this->db->fetchColumn(
            "SELECT loan_no FROM loans WHERE loan_no LIKE ? ORDER BY id DESC LIMIT 1",
            ["LN-{$year}-%"]
        );

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = (int)$m[1] + 1;
        }

        return "LN-{$year}-" . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate loan financials (interest, schedule totals, monthly installment).
     *
     * @param float  $principal    Principal amount
     * @param float  $rate         Annual interest rate as percentage
     * @param int    $termMonths   Loan term in months
     * @param string $interestType flat | reducing_balance | compound
     * @return array Calculated financial figures
     */
    public function calculateLoan(float $principal, float $rate, int $termMonths, string $interestType): array
    {
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
                    // Standard reducing balance EMI formula
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
            'interest_rate'       => $rate,
            'interest_type'       => $interestType,
        ];
    }

    /**
     * Generate repayment schedule for a loan.
     *
     * @param int    $loanId           Loan database ID
     * @param float  $principal        Principal amount
     * @param float  $rate             Annual interest rate %
     * @param int    $termMonths       Loan term in months
     * @param string $interestType     Interest calculation method
     * @param string $firstRepayDate   First repayment due date (Y-m-d)
     */
    public function generateRepaymentSchedule(
        int $loanId,
        float $principal,
        float $rate,
        int $termMonths,
        string $interestType,
        string $firstRepayDate
    ): void {
        // Clear existing schedule for this loan
        $this->db->execute(
            "DELETE FROM loan_repayment_schedules WHERE loan_id = ?",
            [$loanId]
        );

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
                        // Last installment clears remaining balance
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

    /**
     * Get detailed loan with member info, product info, and repayment progress.
     */
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

    /**
     * Get repayment schedule for a loan.
     */
    public function getSchedule(int $loanId): array
    {
        return $this->db->fetchAll("
            SELECT * FROM loan_repayment_schedules
            WHERE loan_id = ?
            ORDER BY installment_no ASC
        ", [$loanId]);
    }

    /**
     * Get guarantors for a loan.
     */
    public function getGuarantors(int $loanId): array
    {
        return $this->db->fetchAll("
            SELECT lg.*, CONCAT(m.first_name,' ',m.last_name) AS guarantor_name, m.phone, m.member_no
            FROM loan_guarantors lg
            JOIN members m ON m.id = lg.guarantor_member_id
            WHERE lg.loan_id = ?
        ", [$loanId]);
    }

    /**
     * Get paginated loan list with filters.
     */
    public function getList(int $page = 1, int $limit = 25, array $filters = []): array
    {
        $where  = ['l.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = 'l.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['member_id'])) {
            $where[]  = 'l.member_id = ?';
            $params[] = $filters['member_id'];
        }
        if (!empty($filters['product_id'])) {
            $where[]  = 'l.loan_product_id = ?';
            $params[] = $filters['product_id'];
        }
        if (!empty($filters['search'])) {
            $like     = '%' . $filters['search'] . '%';
            $where[]  = "(l.loan_no LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ? OR m.member_no LIKE ?)";
            $params   = array_merge($params, [$like, $like, $like]);
        }
        if (!empty($filters['date_from'])) {
            $where[]  = 'l.application_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'l.application_date <= ?';
            $params[] = $filters['date_to'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $offset   = ($page - 1) * $limit;

        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM loans l JOIN members m ON m.id = l.member_id {$whereSql}",
            $params
        );

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

        return [
            'data'      => $data,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $limit,
            'last_page' => (int)ceil($total / $limit),
        ];
    }

    /**
     * Loan portfolio statistics.
     */
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

    /**
     * Mark overdue repayment installments (run via scheduler).
     */
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
