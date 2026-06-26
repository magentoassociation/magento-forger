<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\Action;
use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ScoredEvent;
use App\Services\Leaderboard\EligibilityGate;
use Carbon\Carbon;
use Tests\TestCase;

class EligibilityGateTest extends TestCase
{
    private function event(string $login, Board $board): ScoredEvent
    {
        return new ScoredEvent($login, $board, Action::PR_OPENED, Carbon::now());
    }

    public function test_contributor_events_are_never_gated(): void
    {
        $this->assertTrue((new EligibilityGate([]))->allows($this->event('anyone', Board::CONTRIBUTOR)));
        $this->assertTrue((new EligibilityGate(['mod' => true]))->allows($this->event('anyone', Board::CONTRIBUTOR)));
    }

    public function test_empty_maintainer_roster_allows_all_maintainer_events(): void
    {
        $this->assertTrue((new EligibilityGate([]))->allows($this->event('anyone', Board::MAINTAINER)));
    }

    public function test_maintainer_events_gated_by_roster(): void
    {
        $gate = new EligibilityGate(['mod' => true]);

        $this->assertTrue($gate->allows($this->event('mod', Board::MAINTAINER)));
        $this->assertFalse($gate->allows($this->event('bob', Board::MAINTAINER)));
    }
}
