<?php
namespace App\Helpers;

use Database;

/**
 * AKABBO SOCIAL FUND — SavingsAccountSequence
 *
 * Thread-safe, conflict-free savings account number generator.
 * Uses SELECT … FOR UPDATE but does NOT manage its own transaction.
 * The caller must start a transaction before calling next()/nextFormatted().
 *
 * Format: SAV-YYYYMM001, SAV-YYYYMM002, … SAV-YYYYMM999
 * The YYYYMM is strictly derived from the member's actual 'membership_date', 
 * NOT the system's current date, ensuring historical accuracy.
 */
class SavingsAccountSequence
{
    private static Database $db;

    /**
     * Atomically claim `$count` sequential numbers for a specific month.
     *
     * @param int $count How many numbers to reserve (default 1)
     * @param string|null $yearMonth Optional YYYYMM string. If null, uses current month.
     * @return int First reserved sequence number
     */
    public static function next(int $count = 1, ?string $yearMonth = null): int
    {
        self::$db = Database::getInstance();

        if (!self::$db->inTransaction()) {
            throw new \RuntimeException('SavingsAccountSequence::next() must be called within an active transaction');
        }

        // Normalize the yearMonth parameter
        if ($yearMonth && strlen($yearMonth) > 6) {
            $yearMonth = date('Ym', strtotime($yearMonth));
        } elseif (!$yearMonth) {
            $yearMonth = date('Ym');
        }

        $row = self::$db->fetchOne(
            "SELECT last_seq FROM savings_account_sequence WHERE year_month = ? FOR UPDATE",
            [$yearMonth]
        );

        if (!$row) {
            // Bootstrap: get the highest existing account number for this specific month
            // Prefix 'SAV-YYYYMM' is exactly 10 characters. The sequence starts at index 11.
            $max = (int) self::$db->fetchColumn(
                "SELECT COALESCE(MAX(CAST(SUBSTRING(account_no, 11) AS UNSIGNED)), 0)
                 FROM savings_accounts WHERE account_no LIKE ?",
                ["SAV-{$yearMonth}%"]
            );
            
            self::$db->execute(
                "INSERT INTO savings_account_sequence (year_month, last_seq) VALUES (?, ?)",
                [$yearMonth, $max]
            );
            $row = ['last_seq' => $max];
        }

        $first = (int)$row['last_seq'] + 1;
        $last  = $first + $count - 1;

        self::$db->execute(
            "UPDATE savings_account_sequence SET last_seq = ? WHERE year_month = ?",
            [$last, $yearMonth]
        );

        return $first;
    }

    /**
     * Format a sequence number into the standard account-number string.
     */
    public static function format(int $seq, string $prefix = 'SAV', int $pad = 3, ?string $yearMonth = null): string
    {
        if ($yearMonth && strlen($yearMonth) > 6) {
            $yearMonth = date('Ym', strtotime($yearMonth));
        } elseif (!$yearMonth) {
            $yearMonth = date('Ym');
        }
        
        return $prefix . '-' . $yearMonth . str_pad($seq, $pad, '0', STR_PAD_LEFT);
    }

    /**
     * Convenience: reserve one number and return it as a formatted string.
     * 
     * @param string|null $membershipDate The member's actual joining date (Y-m-d)
     */
    public static function nextFormatted(?string $membershipDate = null, string $prefix = 'SAV'): string
    {
        $yearMonth = $membershipDate ? date('Ym', strtotime($membershipDate)) : date('Ym');
        $seq = self::next(1, $yearMonth);
        return self::format($seq, $prefix, 3, $yearMonth);
    }

    /**
     * Legacy wrapper for backward compatibility. 
     * Now routes to nextFormatted using the provided membership date.
     */
    public static function forMember(int $memberId, ?string $membershipDate = null): string
    {
        return self::nextFormatted($membershipDate);
    }
}