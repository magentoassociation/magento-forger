<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Company;

use App\Models\Company;
use Illuminate\Support\Facades\DB;

class CompanyMergeService
{
    /**
     * Merge all affiliations from $source into $target, then mark $source as rejected.
     *
     * Affiliations that would create a duplicate user-company pair in $target are deleted.
     * The source company is not hard-deleted so it remains auditable.
     */
    public function merge(Company $source, Company $target): void
    {
        DB::transaction(function () use ($source, $target) {
            $existingUserIds = $target->affiliations()->pluck('user_id');

            $source->affiliations()->whereIn('user_id', $existingUserIds)->delete();

            $source->affiliations()->update(['company_id' => $target->id]);

            $source->status = 'rejected';
            $source->save();
        });
    }
}
