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
        public readonly Action $action,
        public readonly CarbonInterface $date,
        public readonly float $impact = 1.0,
        public readonly ?CarbonInterface $attributionDate = null,
        public readonly ?string $title = null,
        public readonly ?string $url = null,
    ) {}

    /**
     * Date used to attribute the event to an organization (point-in-time
     * membership). Defaults to the scoring date, but for events whose scoring
     * date lags the work (e.g. PR merged_at, issue closed_at) this carries the
     * work date so credit lands with the employer at authoring time.
     */
    public function attributionDate(): CarbonInterface
    {
        return $this->attributionDate ?? $this->date;
    }
}
