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
    'version' => 7,

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
        'exact' => ['dependabot[bot]', 'github-actions[bot]', 'm2-assistant', 'magento-github-admin-beta'],
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
            'pr_opened' => 2,              // encourage contributing; merging is out of the contributor's control
            'pr_merged' => 10,             // author bonus, multiplied by impact
            'issue_resolved_by_merge' => 4,
        ],
        'maintainer' => [
            'review_approved' => 3,
            'review_rejected' => 3,
            'review_commented' => 1,
            'approved_then_merged' => 6,   // bonus, multiplied by impact, when an approved PR merges
            'pr_claimed' => 2,             // flat bonus for self-assigning (and then reviewing) a pending-review PR
            'label_applied' => 1,          // triage: applying a label (deduped per actor/target/label)
        ],
    ],

    /*
     * Impact weight from priority labels — NOT lines of code. Every issue/PR
     * event is scaled by its highest "Priority: Px" label (1.0 when unlabeled,
     * so unprioritised work still earns its base). Issues additionally carrying
     * the confirmed label get an additive bonus, but only on issue_opened (it
     * rewards filing a bug a maintainer later confirmed). The resulting factor is
     * clamped to [min, max]. Priority labels are applied by maintainers, so a
     * contributor can't self-inflate their own multiplier.
     * See LeaderboardScorer::impactFromLabels().
     */
    'impact' => [
        'min' => 1.0,
        'max' => 5.5, // P0 (3.5) + confirmed (2.0) is the true ceiling
        'priority' => [
            'Priority: P0' => 3.5,
            'Priority: P1' => 3.0,
            'Priority: P2' => 2.5,
            'Priority: P3' => 2.0,
            'Priority: P4' => 1.5,
        ],
        'confirmed_label' => 'Issue: Confirmed',
        'confirmed_bonus' => 2.0,
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
        'window_days' => 30,
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
     * Rosters whose members may earn maintainer points: the maintainer team and
     * the community-council committee (who hold maintainer rights). Synced by
     * sync:github:teams into role_eligibilities as roles `maintainer` and
     * `community-council`. Contributor points are open to everyone. If both
     * rosters are empty, gating is disabled and everyone counts.
     *
     * Each entry is either a GitHub team slug (string, fetched from the org) or
     * a hard-coded list of logins (array). The council has no dedicated GitHub
     * team — its members are mixed into community-council-ma — so it is listed
     * explicitly here.
     */
    'teams' => [
        'maintainers' => 'community-council-ma',
        'council' => [
            'lfolco',
            'sprankhub',
            'IvanChepurnyi',
            'jissereitsma',
            'rhoerr',
            'furan917',
            'nithinterrific',
        ],
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
