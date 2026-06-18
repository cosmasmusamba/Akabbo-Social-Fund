<?php
namespace App\Helpers;

use Database;

/**
 * AKABBO SOCIAL FUND — MemberSequence
 *
 * Thread-safe, conflict-free member number generator.
 * Uses SELECT … FOR UPDATE but does NOT manage its own transaction.
 * 
 * Format: AKB-YYYYMM001, AKB-YYYYMM002, … AKB-YYYYMM999
 * The YYYYMM is strictly derived from the member's actual 'membership_date', 
 * NOT the system's current date, ensuring historical accuracy.
 */
class MemberSequence
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
            throw new \RuntimeException('MemberSequence::next() must be called within an active transaction');
        }

        // Normalize the yearMonth parameter
        if ($yearMonth && strlen($yearMonth) > 6) {
            // If a full date (Y-m-d) was passed, extract YYYYMM
            $yearMonth = date('Ym', strtotime($yearMonth));
        } elseif (!$yearMonth) {
            // Fallback to current month if not provided
            $yearMonth = date('Ym');
        }

        $row = self::$db->fetchOne(
            "SELECT last_seq FROM member_sequence WHERE year_month = ? FOR UPDATE",
            [$yearMonth]
        );

        if (!$row) {
            // Bootstrap: get the highest existing member number for this specific month
            // Prefix 'AKB-YYYYMM' is exactly 10 characters. The sequence starts at index 11.
            $max = (int) self::$db->fetchColumn(
                "SELECT COALESCE(MAX(CAST(SUBSTRING(member_no, 11) AS UNSIGNED)), 0)
                 FROM members WHERE member_no LIKE ?",
                ["AKB-{$yearMonth}%"]
            );
            
            self::$db->execute(
                "INSERT INTO member_sequence (year_month, last_seq) VALUES (?, ?)",
                [$yearMonth, $max]
            );
            $row = ['last_seq' => $max];
        }

        $first = (int)$row['last_seq'] + 1;
        $last  = $first + $count - 1;

        self::$db->execute(
            "UPDATE member_sequence SET last_seq = ? WHERE year_month = ?",
            [$last, $yearMonth]
        );

        return $first;
    }

    /**
     * Format a sequence number into the standard member-number string.
     */
    public static function format(int $seq, string $prefix = 'AKB', int $pad = 3, ?string $yearMonth = null): string
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
    public static function nextFormatted(?string $membershipDate = null, string $prefix = 'AKB'): string
    {
        $yearMonth = $membershipDate ? date('Ym', strtotime($membershipDate)) : date('Ym');
        $seq = self::next(1, $yearMonth);
        return self::format($seq, $prefix, 3, $yearMonth);
    }

    /**
     * Return an array of N formatted member numbers in one DB round-trip.
     */
    public static function batch(int $count, ?string $membershipDate = null, string $prefix = 'AKB'): array
    {
        $yearMonth = $membershipDate ? date('Ym', strtotime($membershipDate)) : date('Ym');
        $first = self::next($count, $yearMonth);
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = self::format($first + $i, $prefix, 3, $yearMonth);
        }
        return $result;
    }

    public static function isUnique(string $memberNo): bool
    {
        $db = Database::getInstance();
        return (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM members WHERE member_no = ?", [$memberNo]
        ) === 0;
    }

    public static function peek(?string $membershipDate = null): int
    {
        $db = Database::getInstance();
        $yearMonth = $membershipDate ? date('Ym', strtotime($membershipDate)) : date('Ym');
        $row = $db->fetchOne("SELECT last_seq FROM member_sequence WHERE year_month = ?", [$yearMonth]);
        return $row ? (int)$row['last_seq'] : 0;
    }
}