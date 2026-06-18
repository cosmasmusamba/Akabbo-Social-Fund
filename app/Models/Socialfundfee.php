<?php
namespace App\Models;

/**
 * AKABBO SOCIAL FUND — SocialFundFee Model
 *
 * Configurable monthly/periodic fee charged to members,
 * with payment tracking and arrears management.
 */
class SocialFundFee extends BaseModel
{
    protected string $table = 'social_fund_fees';

    public function getActive(): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM social_fund_fees WHERE status='active' ORDER BY id DESC LIMIT 1"
        );
    }

    public function getAll(): array
    {
        return $this->db->fetchAll("
            SELECT f.*,
                   CONCAT(u.first_name,' ',u.last_name) AS created_by_name
            FROM social_fund_fees f
            JOIN users u ON u.id = f.created_by
            ORDER BY f.created_at DESC
        ");
    }

    // ── Generate fee records for all active members ───────────────
    public function generateMonthlyFees(int $feeId, int $month, int $year, int $createdBy): array
    {
        $fee = $this->find($feeId);
        if (!$fee) throw new \RuntimeException("Fee ID {$feeId} not found.");

        $memberSql = "SELECT m.id FROM members m";
        if ($fee['applies_to'] === 'shareholders') {
            $memberSql .= " WHERE m.status='active' AND m.is_shareholder=1 AND m.deleted_at IS NULL";
        } elseif ($fee['applies_to'] === 'non_shareholders') {
            $memberSql .= " WHERE m.status='active' AND m.is_shareholder=0 AND m.deleted_at IS NULL";
        } else {
            $memberSql .= " WHERE m.status='active' AND m.deleted_at IS NULL";
        }

        $members  = $this->db->fetchAll($memberSql);
        $inserted = 0;
        $skipped  = 0;

        foreach ($members as $m) {
            $exists = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM social_fund_fee_payments WHERE fee_id=? AND member_id=? AND period_month=? AND period_year=?",
                [$feeId, $m['id'], $month, $year]
            );
            if ($exists) { $skipped++; continue; }

            $this->db->execute("
                INSERT INTO social_fund_fee_payments
                    (fee_id, member_id, period_month, period_year, amount_due, status, created_by)
                VALUES (?, ?, ?, ?, ?, 'pending', ?)
            ", [$feeId, $m['id'], $month, $year, $fee['amount'], $createdBy]);
            $inserted++;
        }

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    // ── Record a payment ─────────────────────────────────────────
    public function recordPayment(int $paymentId, float $amountPaid, float $penaltyPaid, string $method, int $paidBy): void
    {
        $payment = $this->db->fetchOne("SELECT * FROM social_fund_fee_payments WHERE id=?", [$paymentId]);
        if (!$payment) throw new \RuntimeException("Payment record not found.");

        $newStatus = ($amountPaid >= $payment['amount_due']) ? 'paid' : 'partial';

        $this->db->execute("
            UPDATE social_fund_fee_payments
            SET amount_paid   = amount_paid + ?,
                penalty_paid  = penalty_paid + ?,
                paid_date     = CASE WHEN paid_date IS NULL THEN CURDATE() ELSE paid_date END,
                payment_method= ?,
                status        = ?,
                updated_at    = NOW()
            WHERE id = ?
        ", [$amountPaid, $penaltyPaid, $method, $newStatus, $paymentId]);
    }

    // ── Mark overdue ─────────────────────────────────────────────
    public function markOverdue(): int
    {
        return $this->db->execute("
            UPDATE social_fund_fee_payments sfp
            JOIN social_fund_fees sff ON sff.id = sfp.fee_id
            SET sfp.status    = 'overdue',
                sfp.updated_at = NOW()
            WHERE sfp.status  = 'pending'
              AND CURDATE() > DATE(CONCAT(sfp.period_year,'-',LPAD(sfp.period_month,2,'0'),'-',
                                  LPAD(sff.due_day + sff.grace_days,2,'0')))
        ");
    }

    // ── Reports ──────────────────────────────────────────────────
    public function getPaymentSummary(int $feeId, int $month, int $year): array
    {
        return $this->db->fetchOne("
            SELECT
                COUNT(*)                        AS total_members,
                SUM(status='paid')              AS paid_count,
                SUM(status='partial')           AS partial_count,
                SUM(status='overdue')           AS overdue_count,
                SUM(status='pending')           AS pending_count,
                SUM(status='waived')            AS waived_count,
                COALESCE(SUM(amount_due),0)     AS total_due,
                COALESCE(SUM(amount_paid),0)    AS total_collected,
                COALESCE(SUM(penalty_paid),0)   AS total_penalties,
                COALESCE(SUM(amount_due - amount_paid),0) AS total_arrears
            FROM social_fund_fee_payments
            WHERE fee_id=? AND period_month=? AND period_year=?
        ", [$feeId, $month, $year]);
    }

    public function getMemberArrears(int $feeId): array
    {
        return $this->db->fetchAll("
            SELECT sfp.*, CONCAT(m.first_name,' ',m.last_name) AS member_name,
                   m.member_no, m.phone,
                   (sfp.amount_due - sfp.amount_paid) AS balance_due
            FROM social_fund_fee_payments sfp
            JOIN members m ON m.id = sfp.member_id
            WHERE sfp.fee_id=? AND sfp.status IN ('overdue','pending','partial')
            ORDER BY sfp.period_year ASC, sfp.period_month ASC
        ", [$feeId]);
    }

    public function getMemberPaymentHistory(int $memberId): array
    {
        return $this->db->fetchAll("
            SELECT sfp.*, sff.name AS fee_name, sff.amount AS fee_amount
            FROM social_fund_fee_payments sfp
            JOIN social_fund_fees sff ON sff.id = sfp.fee_id
            WHERE sfp.member_id = ?
            ORDER BY sfp.period_year DESC, sfp.period_month DESC
        ", [$memberId]);
    }
}