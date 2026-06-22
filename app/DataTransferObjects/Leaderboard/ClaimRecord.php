<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\DataTransferObjects\Leaderboard;

use Carbon\CarbonInterface;

/**
 * A maintainer self-assigning as reviewer on a PR, with the timing needed to
 * derive review latency: when the PR entered the review pool, when it was
 * claimed, and when the maintainer first reviewed it.
 */
final class ClaimRecord
{
    public function __construct(
        public readonly int $prNumber,
        public readonly string $maintainer,
        public readonly CarbonInterface $claimedAt,
        public readonly ?CarbonInterface $pendingReviewAt,
        public readonly ?CarbonInterface $firstReviewAt,
    ) {}
}
