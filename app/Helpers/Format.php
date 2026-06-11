<?php
namespace App\Helpers;

/**
 * AKABBO SOCIAL FUND
 * Format Helper
 *
 * Utility functions for formatting currency, dates, numbers,
 * file sizes, and other display values.
 */
class Format
{
    /**
     * Format a number as a currency amount with the system currency.
     *
     * @param float  $amount        Amount to format
     * @param string $currencySymbol Override currency symbol
     * @param int    $decimals      Decimal places
     * @return string Formatted currency string (e.g. "USh 1,250,000.00")
     */
    public static function currency(float $amount, string $currencySymbol = DEFAULT_CURRENCY_SYMBOL, int $decimals = 2): string
    {
        return $currencySymbol . ' ' . number_format($amount, $decimals, '.', ',');
    }

    /**
     * Format a currency amount for compact display (e.g. 1.25M, 450K).
     */
    public static function currencyCompact(float $amount): string
    {
        $symbol = Settings::get('currency_symbol') ?? DEFAULT_CURRENCY_SYMBOL;
        if (abs($amount) >= 1_000_000_000) {
            return $symbol . ' ' . number_format($amount / 1_000_000_000, 1) . 'B';
        }
        if (abs($amount) >= 1_000_000) {
            return $symbol . ' ' . number_format($amount / 1_000_000, 1) . 'M';
        }
        if (abs($amount) >= 1_000) {
            return $symbol . ' ' . number_format($amount / 1_000, 1) . 'K';
        }
        return self::currency($amount);
    }

    /**
     * Format a date string to a readable format.
     *
     * @param string|null $date   Input date (Y-m-d or Y-m-d H:i:s)
     * @param string      $format Output format (default: d M Y)
     * @return string Formatted date
     */
    public static function date(?string $date, string $format = 'd M Y'): string
    {
        if (empty($date) || $date === '0000-00-00') {
            return '—';
        }
        try {
            return (new \DateTime($date))->format($format);
        } catch (\Exception) {
            return $date;
        }
    }

    /**
     * Format a datetime string.
     */
    public static function datetime(?string $datetime, string $format = 'd M Y, H:i'): string
    {
        return self::date($datetime, $format);
    }

    /**
     * Return a human-readable "time ago" string.
     *
     * @param string $datetime ISO datetime string
     * @return string e.g. "2 hours ago", "yesterday"
     */
    public static function timeAgo(string $datetime): string
    {
        $now  = new \DateTime();
        $past = new \DateTime($datetime);
        $diff = $now->diff($past);

        if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        if ($diff->d > 6) return 'last week';
        if ($diff->d > 1) return $diff->d . ' days ago';
        if ($diff->d === 1) return 'yesterday';
        if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        return 'just now';
    }

    /**
     * Format a number with thousands separators.
     */
    public static function number(float $number, int $decimals = 0): string
    {
        return number_format($number, $decimals, '.', ',');
    }

    /**
     * Format a percentage value.
     */
    public static function percentage(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals) . '%';
    }

    /**
     * Format a file size in human-readable form.
     */
    public static function fileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Truncate a string to a maximum length, appending an ellipsis.
     */
    public static function truncate(string $text, int $maxLength = 60, string $suffix = '…'): string
    {
        $text = strip_tags($text);
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        return mb_substr($text, 0, $maxLength - mb_strlen($suffix)) . $suffix;
    }

    /**
     * Return a CSS badge class based on a status string.
     * Used for consistent status pill rendering across the UI.
     */
    public static function statusBadge(string $status): array
    {
        $map = [
            // Member / loan statuses
            'active'         => ['class' => 'bg-emerald-100 text-emerald-800 border-emerald-200', 'label' => 'Active'],
            'inactive'       => ['class' => 'bg-slate-100 text-slate-600 border-slate-200',       'label' => 'Inactive'],
            'suspended'      => ['class' => 'bg-amber-100 text-amber-800 border-amber-200',        'label' => 'Suspended'],
            'exited'         => ['class' => 'bg-red-100 text-red-700 border-red-200',              'label' => 'Exited'],
            'locked'         => ['class' => 'bg-red-100 text-red-700 border-red-200',              'label' => 'Locked'],
            // Loan statuses
            'draft'          => ['class' => 'bg-slate-100 text-slate-600 border-slate-200',       'label' => 'Draft'],
            'pending'        => ['class' => 'bg-amber-100 text-amber-800 border-amber-200',        'label' => 'Pending'],
            'approved'       => ['class' => 'bg-blue-100 text-blue-800 border-blue-200',           'label' => 'Approved'],
            'rejected'       => ['class' => 'bg-red-100 text-red-700 border-red-200',              'label' => 'Rejected'],
            'disbursed'      => ['class' => 'bg-indigo-100 text-indigo-800 border-indigo-200',     'label' => 'Disbursed'],
            'completed'      => ['class' => 'bg-emerald-100 text-emerald-800 border-emerald-200',  'label' => 'Completed'],
            'defaulted'      => ['class' => 'bg-red-100 text-red-800 border-red-200',              'label' => 'Defaulted'],
            'written_off'    => ['class' => 'bg-zinc-100 text-zinc-500 border-zinc-200',           'label' => 'Written Off'],
            'cancelled'      => ['class' => 'bg-zinc-100 text-zinc-500 border-zinc-200',           'label' => 'Cancelled'],
            // Transaction statuses
            'completed_txn'  => ['class' => 'bg-emerald-100 text-emerald-800 border-emerald-200',  'label' => 'Completed'],
            'reversed'       => ['class' => 'bg-purple-100 text-purple-800 border-purple-200',     'label' => 'Reversed'],
            // Repayment
            'upcoming'       => ['class' => 'bg-blue-50 text-blue-700 border-blue-100',            'label' => 'Upcoming'],
            'due'            => ['class' => 'bg-amber-100 text-amber-700 border-amber-200',         'label' => 'Due'],
            'overdue'        => ['class' => 'bg-red-100 text-red-700 border-red-200',               'label' => 'Overdue'],
            'paid'           => ['class' => 'bg-emerald-100 text-emerald-800 border-emerald-200',   'label' => 'Paid'],
            'partially_paid' => ['class' => 'bg-sky-100 text-sky-700 border-sky-200',               'label' => 'Partial'],
            'waived'         => ['class' => 'bg-purple-100 text-purple-700 border-purple-200',      'label' => 'Waived'],
        ];

        return $map[$status] ?? ['class' => 'bg-slate-100 text-slate-500 border-slate-200', 'label' => ucfirst(str_replace('_', ' ', $status))];
    }

    /**
     * Generate a status badge HTML string.
     */
    public static function statusPill(string $status): string
    {
        $badge = self::statusBadge($status);
        return sprintf(
            '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border %s">%s</span>',
            $badge['class'],
            $badge['label']
        );
    }

    /**
     * Mask a sensitive value for display (e.g. national ID, phone).
     * Shows the first and last few characters with asterisks in between.
     */
    public static function mask(string $value, int $showStart = 3, int $showEnd = 2): string
    {
        $len = strlen($value);
        if ($len <= $showStart + $showEnd) {
            return str_repeat('*', $len);
        }
        return substr($value, 0, $showStart)
             . str_repeat('*', $len - $showStart - $showEnd)
             . substr($value, -$showEnd);
    }

    /**
     * Convert a snake_case or kebab-case string to Title Case.
     */
    public static function titleCase(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }

    /**
     * Generate initials from a full name (used for avatar placeholders).
     */
    public static function initials(string $fullName, int $chars = 2): string
    {
        $parts    = array_filter(explode(' ', trim($fullName)));
        $initials = '';
        foreach (array_slice($parts, 0, $chars) as $part) {
            $initials .= strtoupper($part[0]);
        }
        return $initials ?: '?';
    }
}
