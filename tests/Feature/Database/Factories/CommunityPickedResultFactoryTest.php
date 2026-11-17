<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Database\Factories;

use App\Models\CommunityPickedResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityPickedResultFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function testDefaultRecordsGetDistinctFirstOfMonthValues(): void
    {
        $results = CommunityPickedResult::factory()->count(5)->create();

        $this->assertCount(5, $results->pluck('month')->map->toDateString()->unique());
        $results->each(function (CommunityPickedResult $result): void {
            $this->assertSame(1, $result->month->day);
            $this->assertTrue($result->month->isBefore(now()->startOfMonth()));
        });
    }

    public function testPostedOnAndImagePathFollowASuppliedMonth(): void
    {
        $result = CommunityPickedResult::factory()->create(['month' => '2020-05-01']);

        $this->assertSame('2020-06-01', $result->posted_on->toDateString());
        $this->assertSame('community-picked/2020-05.png', $result->image_path);
    }

    public function testKeepsAllExplicitOverrides(): void
    {
        $result = CommunityPickedResult::factory()->create([
            'month' => '2020-05-01',
            'posted_on' => '2020-07-15',
            'image_path' => 'community-picked/may.png',
        ]);

        $this->assertSame('2020-05-01', $result->month->toDateString());
        $this->assertSame('2020-07-15', $result->posted_on->toDateString());
        $this->assertSame('community-picked/may.png', $result->image_path);
    }
}
