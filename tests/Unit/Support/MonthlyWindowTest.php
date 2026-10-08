<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\MonthlyWindow;
use Carbon\Carbon;
use Tests\TestCase;

class MonthlyWindowTest extends TestCase
{
    public function testAllowedReturnsCurrentMonthFirstNewestToOldest(): void
    {
        $months = MonthlyWindow::allowed(3, Carbon::parse('2026-07-15T10:00:00Z'));

        $this->assertSame(['2026-07', '2026-06', '2026-05'], $months);
    }

    public function testAllowedCrossesYearBoundary(): void
    {
        $months = MonthlyWindow::allowed(3, Carbon::parse('2026-01-10T00:00:00Z'));

        $this->assertSame(['2026-01', '2025-12', '2025-11'], $months);
    }

    public function testAllowedHandlesMonthEndWithoutOverflow(): void
    {
        // Naive subMonth from Mar 31 would skip February; startOfMonth avoids it.
        $months = MonthlyWindow::allowed(2, Carbon::parse('2026-03-31T23:59:59Z'));

        $this->assertSame(['2026-03', '2026-02'], $months);
    }

    public function testAllowedUsesUtcForTheCurrentMonth(): void
    {
        // 2026-07-01T00:30+02:00 is still 2026-06-30 in UTC.
        $months = MonthlyWindow::allowed(1, Carbon::parse('2026-07-01T00:30:00+02:00'));

        $this->assertSame(['2026-06'], $months);
    }

    public function testLabelFormatsMonthKey(): void
    {
        $this->assertSame('Jul 2026', MonthlyWindow::label('2026-07'));
        $this->assertSame('Dec 2025', MonthlyWindow::label('2025-12'));
    }
}
