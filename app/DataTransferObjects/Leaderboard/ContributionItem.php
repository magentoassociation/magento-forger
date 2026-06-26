<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\DataTransferObjects\Leaderboard;

use Carbon\CarbonInterface;

/**
 * A single scorable contribution with display detail (title + GitHub URL), for
 * the per-user score drill-down. Points are computed from board/action/impact/date.
 */
final class ContributionItem
{
    public function __construct(
        public readonly Board $board,
        public readonly Action $action,
        public readonly CarbonInterface $date,
        public readonly float $impact,
        public readonly string $title,
        public readonly string $url,
    ) {}
}
