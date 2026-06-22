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
     * The label Adobe applies when a PR enters the review pool. Time a PR spends
     * with this label before a maintainer self-assigns drives the pr_claimed bonus.
     */
    'pending_review_label' => 'Progress: pending review',

    /*
     * Accounts excluded from every leaderboard (bots / automation). Single source
     * of truth — consumed via App\Support\BotFilter for both OpenSearch filtering
     * and in-PHP checks. Add a bot here and it applies everywhere.
     */
    'bots' => [
        'exact' => ['dependabot[bot]', 'github-actions[bot]', 'm2-assistant'],
        'prefixes' => ['engcom-', 'magento-automated', 'm2-community', 'github-', 'ct-prd-projects', 'copilot-pull-request-reviewer'],
    ],

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
            'approved_then_merged' => 6,   // bonus, multiplied by impact, when an approved PR merges
            'pr_claimed' => 2,             // bonus, multiplied by staleness, for self-assigning a pending-review PR
            'label_applied' => 1,          // triage: applying a label (deduped per actor/target/label)
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

    /*
     * Triage scoring. Labels in this list are not credited as triage work —
     * default excludes Adobe's automated pending-review label.
     */
    'triage' => [
        'excluded_labels' => [
            'Progress: pending review',
        ],
    ],
];
