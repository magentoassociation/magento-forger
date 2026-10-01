<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Http\View;

use App\DataTransferObjects\Misc\InfoText;
use Tests\TestCase;

class ByMonthTimelineTest extends TestCase
{
    private int $currentYear;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->currentYear = (int) date('Y');
    }

    public function test_timeline_orders_oldest_year_first_regardless_of_input_order(): void
    {
        $html = $this->render();

        $old = strpos($html, 'data-year="'.($this->currentYear - 3).'"');
        $new = strpos($html, 'data-year="'.$this->currentYear.'"');

        $this->assertNotFalse($old);
        $this->assertNotFalse($new);
        $this->assertLessThan($new, $old, 'Oldest year must render to the left of the current year.');
    }

    public function test_bar_height_is_a_ratio_against_the_all_years_max(): void
    {
        $html = $this->render();

        // Current-year January is the page max (120) -> ratio 1, hottest bucket.
        $this->assertStringContainsString('--bh: 1.0000', $html);
        $this->assertStringContainsString('#f26322', $html);
        // The older year's 50 against max 120 -> sqrt(50/120) = 0.6455.
        $this->assertStringContainsString('--bh: 0.6455', $html);
    }

    public function test_zero_month_renders_a_stub_not_a_link(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('bm-bar--zero', $html);
    }

    public function test_picker_shows_the_current_year_only(): void
    {
        $html = $this->render();

        $picker = substr($html, (int) strpos($html, 'class="bm-picker"'));

        $this->assertStringContainsString('class="bm-picker-year">'.$this->currentYear, $picker);
        // The older year's only data point (50) must not leak into the current-year picker.
        $this->assertStringNotContainsString('>50<', $picker);
    }

    public function test_range_row_is_present_for_the_scroll_controls(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data-bm-range', $html);
        $this->assertStringContainsString('data-bm-earlier', $html);
        $this->assertStringContainsString('data-bm-later', $html);
    }

    /**
     * Render the component with synthetic data: an old year (count 50, June) and the current
     * year (max 120 in January, a zero month in February), passed newest-first to prove sorting.
     */
    private function render(): string
    {
        $rows = [
            $this->year($this->currentYear, 120, ['01' => 120, '02' => 0]),
            $this->year($this->currentYear - 3, 50, ['06' => 50]),
        ];

        return view('components.by-month', [
            'rows' => $rows,
            'info' => new InfoText('Why', ['Because.']),
            'noun' => 'issues',
            'linkPath' => 'issues',
            'qType' => 'issue',
        ])->render();
    }

    /**
     * @param array<string, int> $counts month_number => total
     * @return array{year: int, total: int, months: array<string, array{month_number: string, total: int, start: string, end: string}>}
     */
    private function year(int $year, int $total, array $counts): array
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $key = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $months[$key] = [
                'month_number' => $key,
                'total' => $counts[$key] ?? 0,
                'start' => $year.'-'.$key.'-01T00:00:00Z',
                'end' => $year.'-'.$key.'-28T23:59:59Z',
            ];
        }

        return ['year' => $year, 'total' => $total, 'months' => $months];
    }
}
