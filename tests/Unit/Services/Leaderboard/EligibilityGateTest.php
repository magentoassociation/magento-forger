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

    public function testContributorEventsAreNeverGated(): void
    {
        $this->assertTrue((new EligibilityGate([]))->allows($this->event('anyone', Board::CONTRIBUTOR)));
        $this->assertTrue((new EligibilityGate(['mod' => true]))->allows($this->event('anyone', Board::CONTRIBUTOR)));
    }

    public function testEmptyMaintainerRosterAllowsAllMaintainerEvents(): void
    {
        $this->assertTrue((new EligibilityGate([]))->allows($this->event('anyone', Board::MAINTAINER)));
    }

    public function testMaintainerEventsGatedByRoster(): void
    {
        $gate = new EligibilityGate(['mod' => true]);

        $this->assertTrue($gate->allows($this->event('mod', Board::MAINTAINER)));
        $this->assertFalse($gate->allows($this->event('bob', Board::MAINTAINER)));
    }
}
