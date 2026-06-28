<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\Action;
use App\DataTransferObjects\Leaderboard\Board;
use App\Services\Leaderboard\ScoredEventReader;
use Carbon\Carbon;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class ScoredEventReaderTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $excluded
     * @return list<\App\DataTransferObjects\Leaderboard\ScoredEvent>
     */
    private function buildLabelEvents(array $rows, array $excluded = []): array
    {
        $reader = (new ReflectionClass(ScoredEventReader::class))->newInstanceWithoutConstructor();

        return (new ReflectionMethod(ScoredEventReader::class, 'buildLabelEvents'))->invoke($reader, $rows, $excluded);
    }

    public function testDedupesSameActorTargetLabelKeepingEarliest(): void
    {
        $early = Carbon::parse('2026-01-01T00:00:00Z');
        $late = Carbon::parse('2026-02-01T00:00:00Z');

        $events = $this->buildLabelEvents([
            ['actor' => 'mod', 'label' => 'bug', 'target' => 'issue:1', 'date' => $late],
            ['actor' => 'mod', 'label' => 'bug', 'target' => 'issue:1', 'date' => $early],
        ]);

        $this->assertCount(1, $events);
        $this->assertSame(Action::LABEL_APPLIED, $events[0]->action);
        $this->assertSame(Board::MAINTAINER, $events[0]->board);
        $this->assertTrue($events[0]->date->equalTo($early));
    }

    public function testExcludesConfiguredLabels(): void
    {
        $events = $this->buildLabelEvents([
            [
                'actor' => 'mod',
                'label' => 'Progress: pending review',
                'target' => 'pr:5',
                'date' => Carbon::parse('2026-01-01T00:00:00Z'),
            ],
        ], ['Progress: pending review']);

        $this->assertSame([], $events);
    }

    public function testCreditsDistinctActorsSeparately(): void
    {
        $date = Carbon::parse('2026-01-01T00:00:00Z');

        $events = $this->buildLabelEvents([
            ['actor' => 'a', 'label' => 'bug', 'target' => 'issue:1', 'date' => $date],
            ['actor' => 'b', 'label' => 'bug', 'target' => 'issue:1', 'date' => $date],
        ]);

        $this->assertCount(2, $events);
    }

    public function testSkipsRowsMissingActorOrLabel(): void
    {
        $date = Carbon::parse('2026-01-01T00:00:00Z');

        $events = $this->buildLabelEvents([
            ['actor' => null, 'label' => 'bug', 'target' => 'issue:1', 'date' => $date],
            ['actor' => 'mod', 'label' => '', 'target' => 'issue:2', 'date' => $date],
        ]);

        $this->assertSame([], $events);
    }
}
