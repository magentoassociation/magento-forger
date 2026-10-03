<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\DataTransferObjects\Leaderboard;

/**
 * Scored actions. Backed values match the keys in config('leaderboard.weights').
 * Using an enum means an invalid action can't be constructed — a typo is a
 * compile/runtime error rather than a silent zero score.
 */
enum Action: string
{
    case ISSUE_OPENED = 'issue_opened';
    case PR_OPENED = 'pr_opened';
    case PR_MERGED = 'pr_merged';
    case ISSUE_RESOLVED_BY_MERGE = 'issue_resolved_by_merge';
    case REVIEW_APPROVED = 'review_approved';
    case REVIEW_REJECTED = 'review_rejected';
    case REVIEW_COMMENTED = 'review_commented';
    case APPROVED_THEN_MERGED = 'approved_then_merged';
    case PR_CLAIMED = 'pr_claimed';
    case LABEL_APPLIED = 'label_applied';

    /**
     * Human-readable label for display in the UI (board breakdowns, drill-down).
     */
    public function label(): string
    {
        return match ($this) {
            self::ISSUE_OPENED => 'Opened an issue',
            self::PR_OPENED => 'Opened a PR',
            self::PR_MERGED => 'PR was merged',
            self::ISSUE_RESOLVED_BY_MERGE => 'Issue resolved by a merged PR',
            self::REVIEW_APPROVED => 'Approved a PR',
            self::REVIEW_REJECTED => 'Requested changes on a PR',
            self::REVIEW_COMMENTED => 'Commented on a review',
            self::APPROVED_THEN_MERGED => 'Approved a PR that later merged',
            self::PR_CLAIMED => 'Claimed a stale pending-review PR',
            self::LABEL_APPLIED => 'Applied a triage label',
        };
    }

    /**
     * Label for a raw action key, falling back to a humanized key if unknown.
     */
    public static function labelFor(string $action): string
    {
        return self::tryFrom($action)?->label() ?? \Illuminate\Support\Str::headline($action);
    }

    /**
     * Noun-first phrasing for the board Activity cell's accessible name, e.g.
     * "PRs opened 281, issues opened 47". Wording matches the per-board action
     * list in README-leaderboard-pages.md.
     */
    public function countLabel(): string
    {
        return match ($this) {
            self::ISSUE_OPENED => 'issues opened',
            self::PR_OPENED => 'PRs opened',
            self::PR_MERGED => 'PRs merged',
            self::ISSUE_RESOLVED_BY_MERGE => 'issues resolved by a merged PR',
            self::REVIEW_APPROVED => 'PRs approved',
            self::REVIEW_REJECTED => 'changes requested',
            self::REVIEW_COMMENTED => 'review comments',
            self::APPROVED_THEN_MERGED => 'approved PRs that were merged',
            self::PR_CLAIMED => 'stale PRs claimed',
            self::LABEL_APPLIED => 'triage labels applied',
        };
    }

    /**
     * Actions in the order the Activity accessible name lists them: the four
     * contributor actions first, then the six maintainer ones. The two subsets
     * never co-occur on one board, so filtering by a row's breakdown yields that
     * board's order.
     *
     * @return list<self>
     */
    public static function activityOrder(): array
    {
        return [
            self::PR_OPENED, self::PR_MERGED, self::ISSUE_OPENED, self::ISSUE_RESOLVED_BY_MERGE,
            self::REVIEW_APPROVED, self::REVIEW_REJECTED, self::REVIEW_COMMENTED,
            self::APPROVED_THEN_MERGED, self::PR_CLAIMED, self::LABEL_APPLIED,
        ];
    }
}
