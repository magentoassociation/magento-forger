<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GithubUserStat extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_contribution_at' => 'datetime',
            'last_contribution_at' => 'datetime',
            'last_contributor_at' => 'datetime',
            'current_gap_days' => 'integer',
            'current_streak_weeks' => 'integer',
            'longest_streak_weeks' => 'integer',
            'median_time_to_review_hours' => 'float',
            'median_time_to_claim_days' => 'float',
            'reviews_in_window' => 'integer',
            'returned_after_days' => 'integer',
            'contributor_score' => 'float',
            'maintainer_score' => 'float',
            'contributor_score_prev' => 'float',
            'maintainer_score_prev' => 'float',
            'rising_baseline_score' => 'float',
            'computed_at' => 'datetime',
        ];
    }
}
