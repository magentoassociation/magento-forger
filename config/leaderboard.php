<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

return [
    /*
     * Bump when weights change so a recompute can be triggered. Stored alongside
     * computed rows is not required yet, but keep this authoritative.
     */
    'version' => 1,

    /*
     * Base weights per role and action. Contributor and Maintainer scores are
     * always kept separate and never combined. These defaults are intentionally
     * arbitrary starting points — tune them against real output.
     */
    'weights' => [
        'contributor' => [
            'issue_opened' => 1,
            'pr_opened' => 3,
            'pr_merged' => 10,             // author bonus, multiplied by impact
            'issue_resolved_by_merge' => 4,
        ],
        'maintainer' => [
            'review_approved' => 3,
            'review_rejected' => 3,
            'review_commented' => 1,
            // 'approved_then_merged' => 6 // deferred: needs review -> PR merge join
        ],
    ],

    /*
     * Impact weight bounds. Applied to merge bonuses, scaled by PR size
     * (additions + deletions). See LeaderboardScorer::impactFromSize().
     */
    'impact' => [
        'min' => 1.0,
        'max' => 5.0,
    ],

    /*
     * Rolling window with exponential decay. Events older than window_days score
     * zero; half_life_days controls how fast older contributions fade.
     */
    'recency' => [
        'window_days' => 365,
        'half_life_days' => 182,
    ],
];
