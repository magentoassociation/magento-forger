<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class LeaderboardConfigTest extends TestCase
{
    public function test_pending_review_label_is_excluded_from_triage_in_sync(): void
    {
        $label = config('leaderboard.pending_review_label');

        $this->assertNotEmpty($label);
        $this->assertContains(
            $label,
            config('leaderboard.triage.excluded_labels'),
            'The pending-review label must stay excluded from triage scoring; the two settings share one source.',
        );
    }
}
