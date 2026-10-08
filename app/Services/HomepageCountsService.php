<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services;

use App\Queries\Dashboard\OpenLabelsByIssueQuery;
use Illuminate\Support\Facades\Cache;

class HomepageCountsService
{
    private const CACHE_KEY = 'homepage_label_counts';

    private const CACHE_TTL = 3600;

    public function __construct(private readonly OpenLabelsByIssueQuery $query) {}

    /**
     * Open-issue counts keyed by exact label name, cached for one hour.
     *
     * Counts move only as fast as the backend's GitHub sync, so sub-hour staleness is
     * invisible. Only successful lookups are cached, so a transient OpenSearch failure
     * degrades to an empty map (callers render without numbers) instead of poisoning the
     * cache or surfacing an error on the highest-traffic route.
     *
     * @return array<string, int>
     */
    public function labelCounts(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $nested = $this->query->execute();
        } catch (\Throwable) {
            return [];
        }

        $flat = [];
        foreach ($nested as $rows) {
            foreach ($rows as $row) {
                $flat[$row['label']] = $row['count'];
            }
        }

        Cache::put(self::CACHE_KEY, $flat, self::CACHE_TTL);

        return $flat;
    }
}
