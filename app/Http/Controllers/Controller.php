<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\Dashboard\AgeOverTimeQuery;

abstract class Controller
{
    /**
     * Average age at close per month for one index, or null when the search fails so
     * the page hides the chart instead of failing. A missing index is expected in dev
     * and not reported.
     *
     * @return array<string, int|null>|null
     */
    protected function ageOverTime(AgeOverTimeQuery $query, string $index): ?array
    {
        try {
            return $query->execute($index);
        } catch (\Exception $e) {
            if (! $this->isMissingIndex($e)) {
                report($e);
            }

            return null;
        }
    }

    protected function isMissingIndex(\Exception $e): bool
    {
        return str_contains($e->getMessage(), 'index_not_found_exception')
            || str_contains($e->getMessage(), 'no such index');
    }
}
