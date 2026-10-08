<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RoleEligibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import contributor/maintainer eligibility from a CSV (login,role) — the manual
 * alternative to sync:github:teams when the GitHub token can't read org team
 * membership. The roster for each role present in the file is fully replaced, so
 * re-importing the same file is idempotent.
 */
class ImportEligibility extends Command
{
    protected $signature = 'leaderboard:import-eligibility {path : CSV file with login,role columns}';

    protected $description = 'Import contributor/maintainer eligibility from a CSV (login,role).';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return 1;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Could not open: {$path}");

            return 1;
        }

        $byRole = ['contributor' => [], 'maintainer' => [], 'community-council' => []];
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $login = trim((string) ($row[0] ?? ''));
            $role = strtolower(trim((string) ($row[1] ?? '')));

            if ($login === '' || strtolower($login) === 'login') {
                continue; // blank line or header
            }
            if (! isset($byRole[$role])) {
                $skipped++;

                continue;
            }

            $byRole[$role][$login] = true;
        }

        fclose($handle);

        foreach ($byRole as $role => $logins) {
            if ($logins === []) {
                continue;
            }

            $logins = array_keys($logins);

            DB::transaction(function () use ($role, $logins): void {
                RoleEligibility::query()->where('role', $role)->delete();

                foreach (array_chunk($logins, 500) as $chunk) {
                    RoleEligibility::query()->insert(array_map(
                        fn (string $login): array => [
                            'login' => $login,
                            'role' => $role,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        $chunk,
                    ));
                }
            });

            $this->info("{$role}: ".count($logins).' members imported.');
        }

        if ($skipped > 0) {
            $this->warn("{$skipped} rows skipped (role not contributor/maintainer).");
        }

        $this->info('Eligibility imported. Run leaderboard:compute to apply.');

        return self::SUCCESS;
    }
}
