<?php
namespace App\Models;

/**
 * AKABBO SOCIAL FUND — Share Model
 *
 * Handles member share holdings, privilege calculations,
 * share transaction ledger and analytics.
 */
class Share extends BaseModel
{
    protected string $table = 'member_shares';
    protected bool $useSoftDelete = false; // ✅ Correctly disabled

    // ── Share config cache ───────────────────────────────────────
    private ?array $config = null;

    public function getConfig(): array
    {
        if ($this->config === null) {
            $this->config = $this->db->fetchOne("SELECT * FROM share_config ORDER BY id DESC LIMIT 1")
                ?? ['par_value' => 1000, 'loan_rate_discount' => 2.00, 'loan_multiplier_bonus' => 1, 'dividend_rate' => 0];
        }
        return $this->config;
    }

    public function updateConfig(array $data): void
    {
        $this->db->execute(
            "UPDATE share_config SET par_value=?, loan_rate_discount=?, loan_multiplier_bonus=?,
             dividend_rate=?, min_shares=?, max_shares_per_member=?, is_transferable=?, updated_by=?
             WHERE id=1",
            [
                $data['par_value'], $data['loan_rate_discount'], $data['loan_multiplier_bonus'],
                $data['dividend_rate'], $data['min_shares'], $data['max_shares_per_member'],
                $data['is_transferable'] ?? 0, $data['updated_by'],
            ]
        );
        $this->config = null; // bust cache
    }

    // ── Member share record ──────────────────────────────────────
    public function getByMember(int $memberId): ?array
    {
        return $this->db->fetchOne(
            "SELECT ms.*, m.first_name, m.last_name, m.member_no
             FROM member_shares ms JOIN members m ON m.id = ms.member_id
             WHERE ms.member_id = ?",
            [$memberId]
        );
    }

    public function isShareholder(int $memberId): bool
    {
        return (bool) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM member_shares WHERE member_id = ? AND status = 'active' AND shares_held > 0",
            [$memberId]
        );
    }

    // ── Loan privileges for shareholders ─────────────────────────
    public function getLoanPrivileges(int $memberId, float $baseRate, int $baseMultiplier): array
    {
        if (!$this->isShareholder($memberId)) {
            return [
                'interest_rate'  => $baseRate,
                'multiplier'     => $baseMultiplier,
                'is_shareholder' => false,
                'discount'       => 0,
            ];
        }

        $cfg      = $this->getConfig();
        $discount = (float)$cfg['loan_rate_discount'];
        $adjRate  = max(0, $baseRate - $discount);
        $adjMult  = $baseMultiplier + (int)$cfg['loan_multiplier_bonus'];

        $shares = (int)$this->db->fetchColumn(
            "SELECT shares_held FROM member_shares WHERE member_id = ?", [$memberId]
        );
        if ($shares >= 100) {
            $adjRate = max(0, $adjRate - 1.00); // additional 1% for major shareholders
        }

        return [
            'interest_rate'  => round($adjRate, 2),
            'multiplier'     => $adjMult,
            'is_shareholder' => true,
            'discount'       => $discount,
            'shares_held'    => $shares,
        ];
    }

    // ── Generate share txn reference ─────────────────────────────
    public function generateRef(): string
    {
        return 'SHR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
    }

    // ── Analytics ────────────────────────────────────────────────
    public function getSummaryStats(): array
    {
        // ✅ FIX: Removed "WHERE deleted_at IS NULL" since this table doesn't support soft deletes
        return $this->db->fetchOne("
            SELECT
                COUNT(*)                        AS total_shareholders,
                SUM(shares_held)                AS total_shares_issued,
                SUM(total_invested)             AS total_share_capital,
                AVG(shares_held)                AS avg_shares_per_member,
                MAX(shares_held)                AS max_shares_held,
                SUM(status = 'active')          AS active_shareholders
            FROM member_shares
        ") ?? [];
    }

    public function getShareholdersList(int $page = 1, int $limit = 25, array $filters = []): array
    {
        $where  = ['ms.status = "active"'];
        $params = [];

        if (!empty($filters['search'])) {
            $like   = '%' . $filters['search'] . '%';
            $where[]= "(CONCAT(m.first_name,' ',m.last_name) LIKE ? OR m.member_no LIKE ?)";
            $params = array_merge($params, [$like, $like]);
        }

        $ws     = 'WHERE ' . implode(' AND ', $where);
        $offset = ($page - 1) * $limit;
        $total  = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM member_shares ms JOIN members m ON m.id=ms.member_id $ws", $params
        );

        $data = $this->db->fetchAll("
            SELECT ms.*, CONCAT(m.first_name,' ',m.last_name) AS full_name,
                   m.member_no, m.phone, m.status AS member_status
            FROM member_shares ms JOIN members m ON m.id = ms.member_id
            $ws ORDER BY ms.shares_held DESC
            LIMIT $limit OFFSET $offset
        ", $params);

        return [
            'data' => $data, 'total' => $total, 'page' => $page,
            'per_page' => $limit, 'last_page' => (int)ceil($total / $limit),
            'from' => $total > 0 ? $offset + 1 : 0, 'to' => min($offset + $limit, $total)
        ];
    }

    public function getTransactions(int $page = 1, int $limit = 25, array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['member_id'])) {
            $where[]  = 'st.member_id = ?';
            $params[] = $filters['member_id'];
        }
        if (!empty($filters['status'])) {
            $where[]  = 'st.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['txn_type'])) {
            $where[]  = 'st.txn_type = ?';
            $params[] = $filters['txn_type'];
        }

        $ws     = 'WHERE ' . implode(' AND ', $where);
        $offset = ($page - 1) * $limit;
        $total  = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM share_transactions st $ws", $params
        );

        $data = $this->db->fetchAll("
            SELECT st.*,
                   CONCAT(m.first_name,' ',m.last_name)  AS member_name, m.member_no,
                   CONCAT(m2.first_name,' ',m2.last_name) AS to_member_name,
                   CONCAT(u.first_name,' ',u.last_name)   AS created_by_name,
                   CONCAT(ua.first_name,' ',ua.last_name) AS approved_by_name
            FROM share_transactions st
            JOIN members m   ON m.id   = st.member_id
            LEFT JOIN members m2 ON m2.id = st.to_member_id
            LEFT JOIN users u  ON u.id  = st.created_by
            LEFT JOIN users ua ON ua.id = st.approved_by
            $ws ORDER BY st.created_at DESC
            LIMIT $limit OFFSET $offset
        ", $params);

        return [
            'data' => $data, 'total' => $total, 'page' => $page,
            'per_page' => $limit, 'last_page' => (int)ceil($total / $limit),
            'from' => $total > 0 ? $offset + 1 : 0, 'to' => min($offset + $limit, $total)
        ];
    }

    public function getMonthlyReport(): array
    {
        return $this->db->fetchAll("
            SELECT
                DATE_FORMAT(transaction_date,'%Y-%m')   AS period,
                SUM(txn_type='purchase')                AS purchases,
                SUM(txn_type='sale')                    AS sales,
                SUM(CASE WHEN txn_type='purchase' THEN shares_qty ELSE 0 END) AS shares_bought,
                SUM(CASE WHEN txn_type='sale'     THEN shares_qty ELSE 0 END) AS shares_sold,
                SUM(CASE WHEN txn_type='purchase' THEN total_amount ELSE 0 END) AS capital_raised,
                SUM(CASE WHEN txn_type='dividend' THEN total_amount ELSE 0 END) AS dividends_paid
            FROM share_transactions
            WHERE status = 'completed'
              AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(transaction_date,'%Y-%m')
            ORDER BY period DESC
        ");
    }
}