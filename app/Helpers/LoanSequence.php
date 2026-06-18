<?php
namespace App\Helpers;

use Database;

/**
 * AKABBO SOCIAL FUND — LoanSequence
 *
 * Thread-safe, conflict-free loan number generator.
 * Uses SELECT … FOR UPDATE to prevent race conditions during concurrent applications.
 * Format: LN-2025-00001, LN-2025-00002, …
 */
class LoanSequence
{
    private static Database $db;

    public static function next(int $count = 1): int
    {
        self::$db = Database::getInstance();

        if (!self::$db->inTransaction()) {
            throw new \RuntimeException('LoanSequence::next() must be called within an active transaction');
        }

        $year = date('Y');
        $row = self::$db->fetchOne(
            "SELECT last_seq FROM loan_sequence WHERE year = ? FOR UPDATE",
            [$year]
        );

        if (!$row) {
            // Bootstrap: get the highest existing loan number for this year
            $max = (int) self::$db->fetchColumn(
                "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(loan_no,'-',-1) AS UNSIGNED)),0)
                 FROM loans WHERE loan_no LIKE ?",
                ["LN-{$year}-%"]
            );
            self::$db->execute(
                "INSERT INTO loan_sequence (year, last_seq) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE last_seq = ?",
                [$year, $max, $max]
            );
            $row = ['last_seq' => $max];
        }

        $first = (int)$row['last_seq'] + 1;
        $last  = $first + $count - 1;

        self::$db->execute(
            "UPDATE loan_sequence SET last_seq = ? WHERE year = ?",
            [$last, $year]
        );

        return $first;
    }

    public static function format(int $seq, int $year): string
    {
        return "LN-{$year}-" . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    public static function nextFormatted(): string
    {
        return self::format(self::next(1), date('Y'));
    }
}