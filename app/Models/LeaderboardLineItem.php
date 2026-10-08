<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One scored contribution behind a leaderboard score, written by
 * leaderboard:compute so the per-user drill-down reconciles exactly with the
 * board total (rather than re-deriving a different event set on demand).
 */
class LeaderboardLineItem extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'float',
            'points_flat' => 'float',
            'contributed_at' => 'datetime',
            'computed_at' => 'datetime',
        ];
    }
}
