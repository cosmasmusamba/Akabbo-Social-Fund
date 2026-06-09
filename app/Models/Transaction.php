<?php
namespace App\Models;

/**
 * AKABBO SOCIAL FUND — Transaction Model
 */
class Transaction extends BaseModel
{
    protected string $table      = 'transactions';
    protected bool   $useSoftDelete = false;

    public function generateTxnRef(): string
    {
        return 'TXN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    public function getStats(string $dateFrom = null, string $dateTo = null): array
    {
        $from = $dateFrom ?? date('Y-m-01');
        $to   = $dateTo   ?? date('Y-m-d');

        return $this->db->fetchOne("
            SELECT
                COUNT(*)                                    AS total_txns,
                COALESCE(SUM(CASE WHEN txn_type='deposit'          AND status='completed' THEN amount ELSE 0 END),0) AS total_deposits,
                COALESCE(SUM(CASE WHEN txn_type='withdrawal'       AND status='completed' THEN amount ELSE 0 END),0) AS total_withdrawals,
                COALESCE(SUM(CASE WHEN txn_type='loan_disbursement'AND status='completed' THEN amount ELSE 0 END),0) AS total_disbursed,
                COALESCE(SUM(CASE WHEN txn_type='loan_repayment'   AND status='completed' THEN amount ELSE 0 END),0) AS total_repayments,
                SUM(status='pending')   AS pending_count,
                SUM(status='completed') AS completed_count,
                SUM(status='reversed')  AS reversed_count
            FROM transactions
            WHERE transaction_date BETWEEN ? AND ?
        ", [$from, $to]);
    }

    public function getMemberStatement(int $memberId, string $from, string $to): array
    {
        return $this->db->fetchAll("
            SELECT t.*, sa.account_no
            FROM transactions t
            LEFT JOIN savings_accounts sa ON sa.id = t.savings_account_id
            WHERE t.member_id = ?
              AND t.transaction_date BETWEEN ? AND ?
              AND t.status IN ('completed','approved')
            ORDER BY t.transaction_date ASC, t.id ASC
        ", [$memberId, $from, $to]);
    }

    public function getDailyTotals(int $days = 30): array
    {
        return $this->db->fetchAll("
            SELECT
                DATE(transaction_date) AS txn_date,
                COALESCE(SUM(CASE WHEN txn_type IN ('deposit','loan_repayment') THEN amount ELSE 0 END),0) AS inflow,
                COALESCE(SUM(CASE WHEN txn_type IN ('withdrawal','loan_disbursement') THEN amount ELSE 0 END),0) AS outflow
            FROM transactions
            WHERE status = 'completed'
              AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(transaction_date)
            ORDER BY txn_date ASC
        ", [$days]);
    }
}
