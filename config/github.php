<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

return [
    'repo' => env('GITHUB_REPO', 'magento/magento2'),
    'token' => env('GITHUB_TOKEN'),

    // Issues and PRs created before this date (and their events, interactions, reviews
    // and timeline items) are never synced, and `sync:github:purge-history` deletes
    // any already stored. Dec 2014 is when the repo began merging outside PRs.
    'history_start' => env('GITHUB_HISTORY_START', '2014-12-01'),
];
