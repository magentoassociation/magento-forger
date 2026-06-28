<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\ScoredEvent;
use Carbon\CarbonInterface;

/**
 * Rolls per-event scores up to organizations, point-in-time: each event is
 * credited to the org the contributor belonged to on the event's attribution
 * date (the work date, which can predate merged_at/closed_at). Reuses
 * LeaderboardScorer so company scores use the same weight × impact × decay.
 * Events with no resolved org fall into the null ("Unknown") bucket.
 */
class CompanyScoreAggregator
{
    public function __construct(private readonly LeaderboardScorer $scorer) {}

    /**
     * @param  list<ScoredEvent>  $events
     * @return list<array{organization_id: int|null, contributor_score: float, maintainer_score: float, member_count: int}>
     */
    public function aggregate(array $events, CarbonInterface $now, MembershipResolver $resolver): array
    {
        $buckets = [];

        foreach ($events as $event) {
            $orgId = $resolver->resolve($event->login, $event->attributionDate());
            $key = $orgId === null ? '__unknown__' : (string) $orgId;

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'organization_id' => $orgId,
                    'contributor_score' => 0.0,
                    'maintainer_score' => 0.0,
                    'members' => [],
                ];
            }

            $buckets[$key][$event->board->value.'_score'] += $this->scorer->points($event, $now);
            $buckets[$key]['members'][$event->login] = true;
        }

        $result = [];
        foreach ($buckets as $bucket) {
            $result[] = [
                'organization_id' => $bucket['organization_id'],
                'contributor_score' => round($bucket['contributor_score'], 4),
                'maintainer_score' => round($bucket['maintainer_score'], 4),
                'member_count' => count($bucket['members']),
            ];
        }

        return $result;
    }
}
