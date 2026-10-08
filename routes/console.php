<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

use App\Console\Commands\ComputeLeaderboardScores;
use App\Console\Commands\SyncGitHubEvents;
use App\Console\Commands\SyncGitHubInteractions;
use App\Console\Commands\SyncGitHubIssues;
use App\Console\Commands\SyncGitHubPRs;
use App\Console\Commands\SyncGitHubTeams;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune')->daily();

/*
 * The weekly full syncs are staggered an hour apart, in dependency order
 * (issues, PRs, interactions, events), so they do not compete for the same
 * GitHub API rate limit — and so events, which are read against the issues
 * that precede them, run against a freshly imported set.
 *
 * Each incremental sync pauses over its own full run (withoutOverlapping only
 * guards a task against itself, not against the full sync of the same data).
 */

/**
 * Build a skip callback that pauses an incremental sync around its own weekly
 * full sync, on that Sunday only. unlessBetween() is a time-of-day filter, so
 * it would blank out the same band of the clock on all seven days instead.
 *
 * @param  string  $fullSyncTime  Sunday start time of the full sync, as "HH:MM"
 * @param  int  $marginMinutes  How long either side of that start to pause for
 * @return Closure(): bool
 */
$duringWeeklyFullSync = static function (string $fullSyncTime, int $marginMinutes = 20): Closure {
    return static function () use ($fullSyncTime, $marginMinutes): bool {
        $now = Date::now();

        // Check today's and tomorrow's occurrence, so a window that opens
        // before midnight is still matched from the Saturday side of it.
        foreach ([$now->copy(), $now->copy()->addDay()] as $day) {
            if (! $day->isSunday()) {
                continue;
            }

            $fullSync = $day->setTimeFromTimeString($fullSyncTime);

            if ($now->between($fullSync->copy()->subMinutes($marginMinutes), $fullSync->copy()->addMinutes($marginMinutes))) {
                return true;
            }
        }

        return false;
    };
};

// Sync Issues
Schedule::command(SyncGitHubIssues::class)
    ->weeklyOn(0, '00:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Full Sync GitHub Issues using GraphQL');

Schedule::command(SyncGitHubIssues::class, ['--since' => '1 hour ago'])
    ->everyFifteenMinutes()
    ->skip($duringWeeklyFullSync('00:00'))
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Sync GitHub Issues using GraphQL');

// PR Syncs
Schedule::command(SyncGitHubPRs::class)
    ->weeklyOn(0, '01:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Full Sync GitHub Pull Requests using GraphQL');

Schedule::command(SyncGitHubPRs::class, ['--since' => '1 hour ago'])
    ->everyFifteenMinutes()
    ->skip($duringWeeklyFullSync('01:00'))
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Sync GitHub Pull Requests using GraphQL');

// Sync Interactions
Schedule::command(SyncGitHubInteractions::class)
    ->weeklyOn(0, '02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Full Sync GitHub Interactions using GraphQL');

Schedule::command(SyncGitHubInteractions::class, ['--since' => '1 hour ago'])
    ->everyFifteenMinutes()
    ->skip($duringWeeklyFullSync('02:00'))
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Sync GitHub Interactions using GraphQL');

// Sync Events
Schedule::command(SyncGitHubEvents::class)
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Full Sync GitHub Events using GraphQL');

Schedule::command(SyncGitHubEvents::class, ['--since' => '1 hour ago'])
    ->everyFifteenMinutes()
    ->skip($duringWeeklyFullSync('03:00'))
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Sync GitHub Events using GraphQL');

// Sync Teams — rosters move rarely, so once a day (10:00 UTC, the app timezone)
// is enough, and it stays clear of the weekly full sync window.
Schedule::command(SyncGitHubTeams::class)
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Sync the maintainer and community-council team rosters');

/*
 * leaderboard:compute reads what the syncs wrote, so it follows each round of
 * them. The syncs run in the background and the scheduler cannot await them, so
 * the compute is offset rather than chained: ten minutes past each quarter hour
 * for the incremental round, and an hour after the last of the weekly full
 * syncs for the full one.
 */

/**
 * True while any of the weekly full syncs is expected to still be running, so
 * the incremental compute stands down rather than scoring a half-written week.
 */
$duringWeeklyFullSyncs = static function () use ($duringWeeklyFullSync): bool {
    foreach (['00:00', '01:00', '02:00', '03:00'] as $fullSyncTime) {
        if ($duringWeeklyFullSync($fullSyncTime, 60)()) {
            return true;
        }
    }

    return false;
};

/*
 * Both computes run the same command, and the default mutex name is derived
 * from the cron expression as well as the command — so without a shared name
 * the weekly run and an incremental one could overlap each other.
 */
$computeMutex = 'framework'.DIRECTORY_SEPARATOR.'schedule-leaderboard-compute';

Schedule::command(ComputeLeaderboardScores::class)
    ->cron('10-59/15 * * * *')
    ->skip($duringWeeklyFullSyncs)
    ->withoutOverlapping()
    ->createMutexNameUsing($computeMutex)
    ->runInBackground()
    ->description('Compute leaderboard scores after the incremental GitHub syncs');

Schedule::command(ComputeLeaderboardScores::class)
    ->weeklyOn(0, '04:00')
    ->withoutOverlapping()
    ->createMutexNameUsing($computeMutex)
    ->runInBackground()
    ->description('Compute leaderboard scores after the weekly full GitHub syncs');
