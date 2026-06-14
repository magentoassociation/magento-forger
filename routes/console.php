<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

use App\Console\Commands\SyncGitHubIssues;
use App\Console\Commands\SyncGitHubPRs;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune')->daily();

// PR Syncs
Schedule::command(SyncGitHubPRs::class)
    ->weekly()
    ->withoutOverlapping()
    ->runInBackground()
    ->name('sync-github-prs-full')
    ->description('Full Sync GitHub Pull Requests using GraphQL');

Schedule::command(SyncGitHubPRs::class, ['--since' => '1 hour ago'])
    ->everyFifteenMinutes()
    ->unlessBetween('23:40', '00:20')
    ->withoutOverlapping()
    ->runInBackground()
    ->name('sync-github-prs')
    ->description('Sync GitHub Pull Requests using GraphQL');

// Sync Issues
Schedule::command(SyncGitHubIssues::class)
    ->weekly()
    ->withoutOverlapping()
    ->runInBackground()
    ->name('sync-github-issues-full')
    ->description('Full Sync GitHub Issues using GraphQL');

Schedule::command(SyncGitHubIssues::class, ['--since' => '1 hour ago'])
    ->everyFifteenMinutes()
    ->unlessBetween('23:40', '00:20')
    ->withoutOverlapping()
    ->runInBackground()
    ->name('sync-github-issues --since "1 day ago"')
    ->description('Sync GitHub Issues using GraphQL');
