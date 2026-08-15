<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function blackoutProvider(): array
    {
        return [
            // The full sync of the same data is running: pause the incremental.
            'issues paused at its own full sync' => ['sync:github:issues', '2026-08-16 00:10:00', false],
            'issues paused before midnight' => ['sync:github:issues', '2026-08-15 23:50:00', false],
            'prs paused at its own full sync' => ['sync:github:prs', '2026-08-16 01:10:00', false],
            'interactions paused at its own full sync' => ['sync:github:interactions', '2026-08-16 02:10:00', false],
            'events paused at its own full sync' => ['sync:github:events', '2026-08-16 03:10:00', false],

            // Another sync's full run: unrelated incrementals keep going.
            'issues run during the prs full sync' => ['sync:github:issues', '2026-08-16 01:10:00', true],
            'events run during the issues full sync' => ['sync:github:events', '2026-08-16 00:10:00', true],

            // Same time of day, no full sync that night.
            'issues run at the same hour midweek' => ['sync:github:issues', '2026-08-19 00:10:00', true],
            'issues run at the same hour on saturday' => ['sync:github:issues', '2026-08-15 00:10:00', true],
            'events run at the same hour midweek' => ['sync:github:events', '2026-08-19 03:10:00', true],

            // Outside the margin on the Sunday itself.
            'issues run well after their full sync' => ['sync:github:issues', '2026-08-16 00:30:00', true],
            'issues run well before their full sync' => ['sync:github:issues', '2026-08-15 23:30:00', true],
        ];
    }

    #[DataProvider('blackoutProvider')]
    public function testIncrementalSyncPausesOnlyOverItsOwnWeeklyFullSync(
        string $command,
        string $now,
        bool $expectedToRun
    ): void {
        $this->travelTo($now);

        $event = $this->incrementalSyncEvent($command);

        $this->assertSame($expectedToRun, $event->filtersPass($this->app));
    }

    public function testFullSyncsAreStaggeredAnHourApartOnSunday(): void
    {
        $expected = [
            'sync:github:issues' => '0 0 * * 0',
            'sync:github:prs' => '0 1 * * 0',
            'sync:github:interactions' => '0 2 * * 0',
            'sync:github:events' => '0 3 * * 0',
        ];

        foreach ($expected as $command => $expression) {
            $this->assertSame($expression, $this->fullSyncEvent($command)->expression, $command);
        }
    }

    public function testLeaderboardComputeFollowsEachRoundOfSyncs(): void
    {
        // Ten past each quarter hour, after the :00/:15/:30/:45 incrementals.
        $this->assertSame('10-59/15 * * * *', $this->incrementalComputeEvent()->expression);

        // An hour after the last weekly full sync (events, 03:00).
        $this->assertSame('0 4 * * 0', $this->fullComputeEvent()->expression);
    }

    public function testTeamsSyncRunsDailyAtTenUtc(): void
    {
        $event = $this->scheduledEvent('sync:github:teams', static fn (): bool => true);

        $this->assertSame('0 10 * * *', $event->expression);
        $this->assertSame('UTC', $event->timezone ?? config('app.timezone'));
    }

    public function testBothComputesShareOneMutex(): void
    {
        $this->assertSame(
            $this->incrementalComputeEvent()->mutexName(),
            $this->fullComputeEvent()->mutexName()
        );

        // The syncs keep their own, so a compute never blocks one of them.
        $this->assertNotSame(
            $this->incrementalComputeEvent()->mutexName(),
            $this->incrementalSyncEvent('sync:github:issues')->mutexName()
        );
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function incrementalComputeProvider(): array
    {
        return [
            'runs midweek' => ['2026-08-19 00:10:00', true],
            'stands down before the first full sync' => ['2026-08-15 23:10:00', false],
            'stands down between the full syncs' => ['2026-08-16 02:25:00', false],
            'stands down until the last full sync has had its hour' => ['2026-08-16 03:55:00', false],
            'resumes after the full round' => ['2026-08-16 04:10:00', true],
        ];
    }

    #[DataProvider('incrementalComputeProvider')]
    public function testIncrementalComputeStandsDownForTheWeeklyFullSyncs(string $now, bool $expectedToRun): void
    {
        $this->travelTo($now);

        $this->assertSame($expectedToRun, $this->incrementalComputeEvent()->filtersPass($this->app));
    }

    private function incrementalSyncEvent(string $command): Event
    {
        return $this->scheduledEvent(
            $command,
            static fn (Event $event): bool => str_contains($event->command ?? '', '--since')
        );
    }

    private function fullSyncEvent(string $command): Event
    {
        return $this->scheduledEvent(
            $command,
            static fn (Event $event): bool => ! str_contains($event->command ?? '', '--since')
        );
    }

    /**
     * The two computes differ only in when they run, so they are told apart by
     * frequency: the weekly one is the only one pinned to a single day.
     */
    private function incrementalComputeEvent(): Event
    {
        return $this->scheduledEvent(
            'leaderboard:compute',
            static fn (Event $event): bool => str_ends_with($event->expression, '* * *')
        );
    }

    private function fullComputeEvent(): Event
    {
        return $this->scheduledEvent(
            'leaderboard:compute',
            static fn (Event $event): bool => ! str_ends_with($event->expression, '* * *')
        );
    }

    /**
     * @param  callable(Event): bool  $matches
     */
    private function scheduledEvent(string $command, callable $matches): Event
    {
        $found = array_values(array_filter(
            $this->app->make(Schedule::class)->events(),
            static fn (Event $event): bool => str_contains($event->command ?? '', "artisan' {$command}")
                && $matches($event)
        ));

        $this->assertCount(1, $found, "Expected exactly one scheduled {$command} entry to match.");

        return $found[0];
    }
}
