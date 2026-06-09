<?php
namespace App\Helpers;

use Database;

/**
 * AKABBO SOCIAL FUND — MemberSequence
 *
 * Thread-safe, conflict-free member number generator.
 * Uses SELECT … FOR UPDATE but does NOT manage its own transaction.
 * The caller must start a transaction before calling next()/nextFormatted().
 *
 * Format: AKB-00001, AKB-00002, … AKB-99999
 */
class MemberSequence
{
    private static Database $db;

    /**
     * Atomically claim `$count` sequential numbers and return the first one.
     *
     * IMPORTANT: This method does NOT start or commit a transaction.
     * The caller MUST have already called beginTransaction() before invoking this method,
     * and commit() afterwards. The FOR UPDATE lock will hold until the outer transaction ends.
     *
     * @param int $count How many numbers to reserve (default 1)
     * @return int First reserved sequence number
     * @throws \RuntimeException on database error
     */
    public static function next(int $count = 1): int
    {
        self::$db = Database::getInstance();

        // Ensure we are inside a transaction – otherwise FOR UPDATE has no effect
        if (!self::$db->inTransaction()) {
            throw new \RuntimeException('MemberSequence::next() must be called within an active transaction');
        }

        $row = self::$db->fetchOne(
            "SELECT last_seq FROM member_sequence WHERE id = 1 FOR UPDATE"
        );

        if (!$row) {
            // Bootstrap: get the highest existing member number
            $max = (int) self::$db->fetchColumn(
                "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(member_no,'-',-1) AS UNSIGNED)),0)
                 FROM members WHERE member_no REGEXP '^AKB-[0-9]+$'"
            );
            self::$db->execute(
                "INSERT INTO member_sequence (id, last_seq, prefix) VALUES (1, ?, 'AKB')
                 ON DUPLICATE KEY UPDATE last_seq = ?",
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

        return $first;
    }

    /**
     * Format a sequence number into the standard member-number string.
     */
    public static function format(int $seq, string $prefix = 'AKB', int $pad = 5): string
    {
        return $prefix . '-' . str_pad($seq, $pad, '0', STR_PAD_LEFT);
    }

    /**
     * Convenience: reserve one number and return it as a formatted string.
     * Must be called inside an active transaction.
     */
    public static function nextFormatted(string $prefix = 'AKB'): string
    {
        return self::format(self::next(1), $prefix);
    }

    /**
     * Return an array of N formatted member numbers in one DB round-trip.
     * Must be called inside an active transaction.
     *
     * @param int $count
     * @return string[]
     */
    public static function batch(int $count, string $prefix = 'AKB'): array
    {
        $first = self::next($count);
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $result[] = self::format($first + $i, $prefix);
        }
        return $result;
    }

    /**
     * Verify that a given member number does not already exist.
     * (Safety check – normally not needed because sequence guarantees uniqueness)
     */
    public static function isUnique(string $memberNo): bool
    {
        $db = Database::getInstance();
        return (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM members WHERE member_no = ?", [$memberNo]
        ) === 0;
    }

    /**
     * Peek at the current last_seq without incrementing (no transaction needed).
     */
    public static function peek(): int
    {
        $db = Database::getInstance();
        $row = $db->fetchOne("SELECT last_seq FROM member_sequence WHERE id = 1");
        return $row ? (int)$row['last_seq'] : 0;
    }
}