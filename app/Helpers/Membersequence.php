<?php
namespace App\Helpers;

use Database;

/**
 * AKABBO SOCIAL FUND — MemberSequence
 *
 * Thread-safe, conflict-free member number generator.
 *
 * Uses an atomic SELECT … FOR UPDATE + UPDATE to claim the next
 * sequence number inside a transaction, preventing race conditions
 * during bulk imports or concurrent registrations.
 *
 * Format: AKB-00001, AKB-00002, … AKB-99999
 */
class MemberSequence
{
    private static Database $db;

    // ── Get and reserve the next N sequence numbers ───────────────
    /**
     * Atomically claim `$count` sequential numbers and return the
     * first one.  All numbers from returned_value to returned_value+count-1
     * are reserved for the caller.
     *
     * @param  int    $count   How many numbers to reserve (default 1)
     * @return int             First reserved sequence number
     */
    public static function next(int $count = 1): int
    {
        self::$db = Database::getInstance();

        // Lock the row so concurrent calls queue up
        self::$db->beginTransaction();
        try {
            $row = self::$db->fetchOne(
                "SELECT last_seq FROM member_sequence WHERE id = 1 FOR UPDATE"
            );

            if (!$row) {
                // Bootstrap from actual max if table is empty
                $max = (int) self::$db->fetchColumn(
                    "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(member_no,'-',-1) AS UNSIGNED)),0)
                     FROM members WHERE member_no REGEXP '^AKB-[0-9]+$'"
                );
                self::$db->execute(
                    "INSERT INTO member_sequence (last_seq, prefix) VALUES (?, 'AKB') ON DUPLICATE KEY UPDATE last_seq=?",
                    [$max, $max]
                );
                $row = ['last_seq' => $max];
            }

            $first = (int)$row['last_seq'] + 1;
            $last  = $first + $count - 1;

            self::$db->execute(
                "UPDATE member_sequence SET last_seq = ? WHERE id = 1",
                [$last]
            );

            self::$db->commit();
            return $first;

        } catch (\Exception $e) {
            self::$db->rollback();
            throw $e;
        }
    }

    /**
     * Format a sequence number into the standard member-number string.
     *
     * @param  int    $seq     Sequence number
     * @param  string $prefix  Prefix (default AKB)
     * @param  int    $pad     Zero-padding width (default 5)
     */
    public static function format(int $seq, string $prefix = 'AKB', int $pad = 5): string
    {
        return $prefix . '-' . str_pad($seq, $pad, '0', STR_PAD_LEFT);
    }

    /**
     * Convenience: reserve one number and return it as a formatted string.
     */
    public static function nextFormatted(string $prefix = 'AKB'): string
    {
        return self::format(self::next(1), $prefix);
    }

    /**
     * Return an array of N formatted member numbers in one DB round-trip.
     *
     * @param  int    $count
     * @return string[]
     */
    public static function batch(int $count, string $prefix = 'AKB'): array
    {
        $first  = self::next($count);
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = self::format($first + $i, $prefix);
        }
        return $result;
    }

    /**
     * Verify that a given member number does not already exist.
     * Use this as a safety guard after generation.
     */
    public static function isUnique(string $memberNo): bool
    {
        $db = Database::getInstance();
        return (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM members WHERE member_no = ?", [$memberNo]
        ) === 0;
    }

    /**
     * Peek at the current last_seq without incrementing.
     */
    public static function peek(): int
    {
        $db  = Database::getInstance();
        $row = $db->fetchOne("SELECT last_seq FROM member_sequence WHERE id = 1");
        return $row ? (int)$row['last_seq'] : 0;
    }
}