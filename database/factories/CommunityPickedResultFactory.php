<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Database\Factories;

use App\Models\CommunityPickedResult;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        $month = now()->subMonth()->startOfMonth();

        return [
            'month' => $month,
            'posted_on' => $month->copy()->addMonth(),
            'image_path' => 'community-picked/'.$month->format('Y-m').'.png',
        ];
    }
}
