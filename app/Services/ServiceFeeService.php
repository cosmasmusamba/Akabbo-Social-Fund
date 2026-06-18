<?php
namespace App\Services;

use App\Helpers\Format;
use Database;

class ServiceFeeService
{
    private Database $db;
    private NotificationService $notif;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->notif = new NotificationService();
    }

    /**
     * Calculate the fee for a service without processing it (used for UI confirmation).
     */
    public function calculateFee(int $memberId, string $serviceType): array
    {
        $settings = $this->getFeeSettings($serviceType);
        if (!$settings['required']) {
            return ['applicable' => false, 'amount' => 0, 'reason' => 'Fees disabled'];
        }

        if ($this->isExempt($memberId)) {
            return ['applicable' => false, 'amount' => 0, 'reason' => 'User exempt'];
        }

        if ($this->hasFreeLimitRemaining($memberId, $serviceType, $settings)) {
            return ['applicable' => false, 'amount' => 0, 'reason' => 'Free limit remaining'];
        }

        return [
            'applicable' => true,
            'amount' => (float)$settings['amount'],
            'reason' => 'Standard fee'
        ];
    }

    /**
     * Process the fee: deduct balance or create a pending debit.
     */
    public function processFee(int $memberId, string $serviceType, int $userId): array
    {
        $calc = $this->calculateFee($memberId, $serviceType);
        if (!$calc['applicable']) {
            return ['success' => true, 'charged' => false, 'amount' => 0, 'message' => $calc['reason']];
        }

        $feeAmount = $calc['amount'];
        $account = $this->db->fetchOne(
            "SELECT * FROM savings_accounts WHERE member_id = ? AND status = 'active' LIMIT 1", 
            [$memberId]
        );

        if (!$account) {
            $this->createPendingDebit($memberId, $feeAmount, $serviceType . '_fee');
            return ['success' => true, 'charged' => false, 'pending' => true, 'amount' => $feeAmount];
        }

        // Check Available Balance (respecting min savings and loan collateral)
        $activeLoanBalance = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(balance_outstanding),0) FROM loans WHERE member_id = ? AND status IN ('active','disbursed')", 
            [$memberId]
        );
        $settings = $this->getGeneralSettings();
        $multiplier = (int)($settings['max_loan_multiplier'] ?? 3);
        $minRequired = $activeLoanBalance / $multiplier;
        $minSavings = (float)($settings['min_savings'] ?? 10000);
        
        $available = max(0, (float)$account['balance'] - $minRequired - $minSavings);

        if ($available >= $feeAmount) {
            $this->db->beginTransaction();
            try {
                $balanceBefore = (float)$account['balance'];
                $balanceAfter = $balanceBefore - $feeAmount;
                
                $this->db->execute("UPDATE savings_accounts SET balance = ? WHERE id = ?", [$balanceAfter, $account['id']]);
                
                $txnRef = strtoupper(substr($serviceType, 0, 3)) . '-FEE-' . strtoupper(substr(uniqid(), -6));
                $this->db->execute("
                    INSERT INTO transactions (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method, description, balance_before, balance_after, transaction_date, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, 'internal', ?, ?, ?, CURDATE(), 'completed', ?)",
                    [$txnRef, $serviceType . '_fee', $feeAmount, $memberId, $account['id'], ucfirst(str_replace('_', ' ', $serviceType)) . ' service fee', $balanceBefore, $balanceAfter, $userId]
                );
                
                $this->db->commit();
                
                // ✅ FIX: Use multi-channel dispatch instead of sendToUser
                $memberUserId = $this->getMemberUserId($memberId);
                if ($memberUserId > 0) {
                    $this->notif->dispatch(
                        $memberUserId,
                        $serviceType . '_fee_charged',
                        'Service Fee Charged',
                        "Dear Member, a fee of " . Format::currency($feeAmount) . " has been charged to your account for {$serviceType}."
                    );
                }

                return ['success' => true, 'charged' => true, 'amount' => $feeAmount, 'balance_after' => $balanceAfter];
            } catch (\Exception $e) {
                $this->db->rollback();
                return ['success' => false, 'message' => 'Fee processing failed: ' . $e->getMessage()];
            }
        } else {
            $this->createPendingDebit($memberId, $feeAmount, $serviceType . '_fee');
            
            // ✅ FIX: Use multi-channel dispatch instead of sendToUser
            $memberUserId = $this->getMemberUserId($memberId);
            if ($memberUserId > 0) {
                $this->notif->dispatch(
                    $memberUserId,
                    $serviceType . '_fee_pending',
                    'Service Fee Pending',
                    "Dear Member, your {$serviceType} request is successful, but a fee of " . Format::currency($feeAmount) . " could not be charged due to insufficient available balance. It will be deducted from your next deposit."
                );
            }

            return ['success' => true, 'charged' => false, 'pending' => true, 'amount' => $feeAmount];
        }
    }

    /**
     * ✅ FIXED: Settle pending debits by generating ledger entries.
     * This method NO LONGER updates the savings_accounts balance. 
     * The caller (SavingsController) must handle the final balance update to prevent double-counting.
     * 
     * @return array ['fees_settled' => float, 'ledger_entries' => array, 'final_balance' => float]
     */
    public function settlePendingDebits(int $memberId, int $userId, float $currentRunningBalance): array
    {
        $debits = $this->db->fetchAll("SELECT * FROM pending_debits WHERE member_id = ? AND settled_at IS NULL ORDER BY created_at ASC", [$memberId]);
        if (empty($debits)) {
            return ['fees_settled' => 0.0, 'ledger_entries' => [], 'final_balance' => $currentRunningBalance];
        }

        $ledgerEntries = [];
        $totalFees = 0.0;
        $runningBalance = $currentRunningBalance;

        foreach ($debits as $debit) {
            $feeAmount = (float)$debit['amount'];
            $totalFees += $feeAmount;
            
            $balanceBefore = $runningBalance;
            $runningBalance -= $feeAmount;
            
            $txnRef = 'SETTLE-' . strtoupper(substr(uniqid(), -6));
            $ledgerEntries[] = [
                'txn_ref' => $txnRef,
                'txn_type' => $debit['reason'],
                'amount' => $feeAmount,
                'description' => 'Settlement of pending debit: ' . ucfirst(str_replace('_', ' ', $debit['reason'])),
                'balance_before' => $balanceBefore,
                'balance_after' => $runningBalance
            ];
        }

        // Mark debits as settled
        $ids = array_column($debits, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db->execute("UPDATE pending_debits SET settled_at = NOW() WHERE id IN ($placeholders)", $ids);

        return [
            'fees_settled' => $totalFees, 
            'ledger_entries' => $ledgerEntries,
            'final_balance' => $runningBalance
        ];
    }

    // ── Private Helpers ──────────────────────────────────────────

    private function createPendingDebit(int $memberId, float $amount, string $reason): void
    {
        $this->db->execute("INSERT INTO pending_debits (member_id, amount, reason) VALUES (?, ?, ?)", [$memberId, $amount, $reason]);
    }

    private function getFeeSettings(string $serviceType): array
    {
        $rows = $this->db->fetchAll("SELECT `key`, `value` FROM settings WHERE `key` LIKE ?", [$serviceType . '_%']);
        $settings = array_column($rows, 'value', 'key');
        return [
            'required'   => ($settings[$serviceType . '_fee_required'] ?? 'false') === 'true',
            'amount'     => (float)($settings[$serviceType . '_fee_amount'] ?? 0),
            'free_limit' => (int)($settings[$serviceType . '_free_limit'] ?? 0),
            'frequency'  => $settings[$serviceType . '_charge_frequency'] ?? 'per_request',
        ];
    }

    private function getGeneralSettings(): array
    {
        $rows = $this->db->fetchAll("SELECT `key`, `value` FROM settings");
        return array_column($rows, 'value', 'key');
    }

    private function isExempt(int $memberId): bool
    {
        $user = $this->db->fetchOne("SELECT u.role_id, r.slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.member_id = ?", [$memberId]);
        if ($user && in_array($user['slug'], ['super_admin', 'admin', 'auditor', 'finance_manager'])) {
            return true;
        }
        return false;
    }

    private function hasFreeLimitRemaining(int $memberId, string $serviceType, array $settings): bool
    {
        if ($settings['free_limit'] <= 0) return false;
        
        $feeType = $serviceType . '_fee';
        $dateCondition = '';
        if ($settings['frequency'] === 'daily') {
            $dateCondition = "AND DATE(transaction_date) = CURDATE()";
        } elseif ($settings['frequency'] === 'monthly') {
            $dateCondition = "AND MONTH(transaction_date) = MONTH(NOW()) AND YEAR(transaction_date) = YEAR(NOW())";
        }

        $count = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM transactions WHERE member_id = ? AND txn_type = ? {$dateCondition}",
            [$memberId, $feeType]
        );
        
        $pendingCount = 0;
        if ($settings['frequency'] !== 'per_request') {
             $pendingWhere = "member_id = ? AND reason = ?";
             if ($settings['frequency'] === 'daily') $pendingWhere .= " AND DATE(created_at) = CURDATE()";
             if ($settings['frequency'] === 'monthly') $pendingWhere .= " AND MONTH(created_at) = MONTH(NOW())";
             
             $pendingCount = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM pending_debits WHERE {$pendingWhere}", [$memberId, $feeType]);
        }

        return ($count + $pendingCount) < $settings['free_limit'];
    }

    private function getMemberUserId(int $memberId): ?int
    {
        return (int)$this->db->fetchColumn("SELECT user_id FROM members WHERE id = ?", [$memberId]);
    }
}