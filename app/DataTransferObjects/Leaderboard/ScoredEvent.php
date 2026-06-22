<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\DataTransferObjects\Leaderboard;

use Carbon\CarbonInterface;

final class ScoredEvent
{
    public function __construct(
        public readonly string $login,
        public readonly Board $board,
        public readonly string $action,
        public readonly CarbonInterface $date,
        public readonly float $impact = 1.0,
    ) {}
}
