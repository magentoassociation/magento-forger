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
}
