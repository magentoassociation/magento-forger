<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

/*
 * The label Adobe applies when a PR enters the review pool. Defined once here so
 * the claim-detection setting (pending_review_label) and the triage exclusion
 * below can never drift apart.
 */
$pendingReviewLabel = 'Progress: pending review';

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
    'pending_review_label' => $pendingReviewLabel,

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
            $pendingReviewLabel,
        ],
    ],

    /*
     * "New contributor spotlight" lists people whose first-ever contribution
     * landed within this many days. Shared by the compute job (to capture the
     * first contribution's link) and the Highlights query.
     */
    'spotlight' => [
        'window_days' => 30,
    ],

    /*
     * A "comeback" is a contribution that follows a long silence. If a returning
     * contributor's gap (last contribution before the window → their return in the
     * window) is at least this many days, it's recorded as returned_after_days.
     */
    'comeback' => [
        'min_gap_days' => 365,
    ],

    /*
     * "Rising" compares each contributor's current score against their score as
     * of window_days ago (read from github_score_snapshots), so the board can
     * promise a real timeframe instead of "since the last compute run". Snapshots
     * are retained for retention_days.
     */
    'rising' => [
        'window_days' => 7,
        'retention_days' => 60,
    ],

    /*
     * Confidence assigned to memberships auto-suggested from a contributor's
     * GitHub profile company (leaderboard:suggest-memberships). Low, because the
     * field is free text; manual memberships are the source of truth.
     */
    'suggestions' => [
        'confidence' => 30,
    ],

    /*
     * GitHub teams (under the repo owner org) whose members may earn maintainer
     * points: the maintainer team and the community-council committee (who hold
     * maintainer rights). Synced by sync:github:teams into role_eligibilities as
     * roles `maintainer` and `community-council`. Contributor points are open to
     * everyone. If both rosters are empty, gating is disabled and everyone counts.
     */
    'teams' => [
        'maintainers' => 'community-council-ma',
        'council' => 'community-council',
    ],

    /*
     * Monthly leaderboards: per-calendar-month score boards for the trailing
     * months_back months (including the current, partial month). Monthly scores
     * use the same weights and impact scaling as the rolling board but omit
     * recency decay — the calendar month itself is the window.
     */
    'monthly' => [
        'months_back' => 12,
    ],
];
