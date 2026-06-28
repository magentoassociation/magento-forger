<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\UserOrgMembership;
use App\Services\Leaderboard\AuthorCompanyReader;
use App\Support\BotFilter;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Proposes low-confidence org memberships from contributors' GitHub profile
 * `company` field. Suggestions are source=`profile` and never overwrite manual
 * memberships (the source of truth). Re-running refreshes profile suggestions.
 */
class SuggestOrgMemberships extends Command implements Isolatable
{
    protected $signature = 'leaderboard:suggest-memberships';

    protected $description = 'Suggest org memberships from contributors\' GitHub profile company '
        .'(low confidence; never overwrites manual memberships).';

    public function handle(AuthorCompanyReader $reader): int
    {
        $confidence = (int) config('leaderboard.suggestions.confidence', 30);
        $manualLogins = UserOrgMembership::query()->where('source', 'manual')->pluck('login')->flip();

        $created = 0;
        $skipped = 0;

        // Rebuild the whole profile-suggestion set each run: wipe every
        // source=profile row first, then recreate survivors. This also clears
        // stale rows for logins that have dropped out of the reader entirely
        // (e.g. emptied their GitHub company field). Wrapped in a transaction so
        // consumers never observe the momentary empty state.
        DB::transaction(function () use ($reader, $manualLogins, $confidence, &$created, &$skipped): void {
            UserOrgMembership::query()->where('source', 'profile')->delete();

            foreach ($reader->read() as $login => $rawCompany) {
                $name = self::normalizeCompanyName($rawCompany);

                if (BotFilter::isBot($login) || $manualLogins->has($login) || $name === '' || Str::slug($name) === '') {
                    $skipped++;

                    continue;
                }

                $organization = Organization::firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name, 'type' => 'unknown'],
                );

                UserOrgMembership::create([
                    'login' => $login,
                    'organization_id' => $organization->id,
                    'from_date' => null,
                    'to_date' => null,
                    'source' => 'profile',
                    'confidence' => $confidence,
                ]);

                $created++;
            }
        });

        $this->info("Suggested {$created} memberships ({$skipped} skipped).");

        return self::SUCCESS;
    }

    public static function normalizeCompanyName(string $raw): string
    {
        $name = ltrim(trim($raw), '@');
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return trim($name);
    }
}
