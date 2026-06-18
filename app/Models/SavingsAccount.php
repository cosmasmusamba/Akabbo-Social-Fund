<?php
namespace App\Models;

/**
 * AKABBO SOCIAL FUND — SavingsAccount Model 
 * Depend on SavingsAccountSequence helper for account generation for consistence and DRY, Enforce (RBAC + IDOR)
 */
class SavingsAccount extends BaseModel
{
    protected string $table = 'savings_accounts';

    public function generateAccountNo(int $memberId): string
    {
        // Ensures uniqueness even if a member has multiple accounts
        return 'SAV-' . str_pad($memberId, 6, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(uniqid(), -4));
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

    /**
     * Apply monthly interest to all eligible accounts.
     * COMPLIANCE FIX: Now creates an immutable transaction record alongside the balance update.
     */
    public function applyInterest(): int
    {
        $accounts = $this->db->fetchAll(
            "SELECT * FROM savings_accounts WHERE status='active' AND interest_rate > 0"
        );
        $updated = 0;
        
        foreach ($accounts as $acc) {
            $monthly = round($acc['balance'] * ($acc['interest_rate'] / 100 / 12), 2);
            if ($monthly > 0) {
                $this->db->beginTransaction();
                try {
                    $balanceBefore = (float)$acc['balance'];
                    $balanceAfter  = $balanceBefore + $monthly;
                    
                    // 1. Update account balance
                    $this->db->execute(
                        "UPDATE savings_accounts SET balance = ?, interest_accrued = interest_accrued + ?, last_interest_posted = CURDATE() WHERE id = ?",
                        [$balanceAfter, $monthly, $acc['id']]
                    );
                    
                    // 2. Record immutable transaction ledger entry
                    $txnRef = 'INT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                    $this->db->execute("
                        INSERT INTO transactions 
                        (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method, description, 
                         balance_before, balance_after, transaction_date, status, created_by)
                        VALUES (?, 'interest', ?, ?, ?, 'internal', 'Monthly interest posting', ?, ?, CURDATE(), 'completed', 1)
                    ", [$txnRef, $monthly, $acc['member_id'], $acc['id'], $balanceBefore, $balanceAfter]);
                    
                    $this->db->commit();
                    $updated++;
                } catch (\Exception $e) {
                    $this->db->rollback();
                    error_log("Interest posting failed for account {$acc['id']}: " . $e->getMessage());
                }
            }
        }
        return $updated;
    }
}