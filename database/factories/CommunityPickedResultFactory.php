<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Database\Factories;

use App\Models\CommunityPickedResult;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CommunityPickedResult>
 */
class CommunityPickedResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Closures run after overrides merge, so posted_on and image_path follow a supplied month.
        return [
            'month' => fn (): Carbon => now()->startOfMonth()->subMonths(fake()->unique()->numberBetween(1, 600)),
            'posted_on' => fn (array $attributes): Carbon => Carbon::parse($attributes['month'])->addMonth(),
            'image_path' => fn (array $attributes): string => 'community-picked/'.Carbon::parse($attributes['month'])->format('Y-m').'.png',
        ];
    }
}
