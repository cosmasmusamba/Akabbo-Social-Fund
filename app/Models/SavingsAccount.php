<?php
namespace App\Models;

/**
 * AKABBO SOCIAL FUND — SavingsAccount Model
 */
class SavingsAccount extends BaseModel
{
    protected string $table = 'savings_accounts';

    public function generateAccountNo(int $memberId): string
    {
        return 'SAV-' . str_pad($memberId, 6, '0', STR_PAD_LEFT);
    }

    public function getByMember(int $memberId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM savings_accounts WHERE member_id = ? ORDER BY id ASC",
            [$memberId]
        );
    }

    public function getTotalSavings(): float
    {
        return (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(balance),0) FROM savings_accounts WHERE status='active'"
        );
    }

    public function getSummaryStats(): array
    {
        return $this->db->fetchOne("
            SELECT
                COALESCE(SUM(balance),0)       AS total_balance,
                COUNT(*)                        AS total_accounts,
                SUM(status='active')            AS active_accounts,
                SUM(status='dormant')           AS dormant_accounts,
                SUM(status='frozen')            AS frozen_accounts,
                AVG(balance)                    AS avg_balance,
                MAX(balance)                    AS highest_balance
            FROM savings_accounts
            WHERE status != 'closed'
        ");
    }

    public function applyInterest(): int
    {
        // Apply interest to all active accounts that have an interest_rate > 0
        $accounts = $this->db->fetchAll(
            "SELECT * FROM savings_accounts WHERE status='active' AND interest_rate > 0"
        );
        $updated = 0;
        foreach ($accounts as $acc) {
            $monthly = round($acc['balance'] * ($acc['interest_rate'] / 100 / 12), 2);
            if ($monthly > 0) {
                $this->db->execute(
                    "UPDATE savings_accounts SET balance = balance + ? WHERE id = ?",
                    [$monthly, $acc['id']]
                );
                $updated++;
            }
        }
        return $updated;
    }
}
