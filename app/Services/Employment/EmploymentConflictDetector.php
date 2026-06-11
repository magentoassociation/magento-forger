<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Employment;

use Illuminate\Database\Eloquent\Relations\HasMany;

class EmploymentConflictDetector
{
    /**
     * Determine whether a date range conflicts with existing affiliations.
     *
     * @param  HasMany  $affiliations  The user's affiliations relation to query.
     * @param  int|string  $companyId  Company to check for conflicts.
     * @param  string  $startDate  Start of the range being tested.
     * @param  string|null  $endDate  End of the range, or null for open-ended.
     * @param  int|null  $excludeId  Affiliation ID to exclude (for updates).
     */
    public function hasConflict(
        HasMany $affiliations,
        int|string $companyId,
        string $startDate,
        ?string $endDate,
        ?int $excludeId = null
    ): bool {
        $query = $affiliations
            ->where('company_id', $companyId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($inner) use ($startDate) {
                    $inner->whereNull('end_date')
                        ->orWhere('end_date', '>=', $startDate);
                })
                    ->where('start_date', '<=', $endDate ?? now());
            });

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
