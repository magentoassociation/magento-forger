<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * The set of calendar months a monthly leaderboard covers: the current month
 * plus the previous months_back - 1, newest first, as UTC 'Y-m' strings. Shared
 * by the compute command (which months to write) and the controller (which
 * months are valid to view and to render in the month picker) so both agree.
 */
class MonthlyWindow
{
    /**
     * @return list<string> 'Y-m' months, newest first, in UTC
     */
    public static function allowed(?int $monthsBack = null, ?CarbonInterface $now = null): array
    {
        $count = max(1, $monthsBack ?? (int) config('leaderboard.monthly.months_back', 12));
        $cursor = ($now ? $now->copy() : Carbon::now())->utc()->startOfMonth();

        $months = [];
        for ($i = 0; $i < $count; $i++) {
            $months[] = $cursor->format('Y-m');
            $cursor->subMonthNoOverflow();
        }

        return $months;
    }

    /**
     * Human label for a 'Y-m' month key, e.g. '2026-07' => 'Jul 2026'.
     */
    public static function label(string $ym): string
    {
        return Carbon::createFromFormat('!Y-m', $ym)->format('M Y');
    }
}
