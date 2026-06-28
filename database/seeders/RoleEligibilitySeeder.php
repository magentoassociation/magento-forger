<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RoleEligibility;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleEligibilitySeeder extends Seeder
{
    /**
     * Community Council (maintainer) roster. Idempotent — safe to re-run.
     * Keep in sync with sync:github:teams / leaderboard:import-eligibility.
     *
     * @var list<string>
     */
    private array $maintainers = [
        'hostep',
        'IvanChepurnyi',
        'sprankhub',
        'Den4ik',
        'MagePsycho',
        'nuzil',
        'lfolco',
        'ryansunxl',
        'ihor-sviziev',
        'ilnytskyi',
        'miguelbalparda',
        'nuovecode',
        'TuVanDev',
        'edenduong',
        'swnsma',
        'AleksLi',
        'andrewbess',
        'mfickers',
        'rhoerr',
        'furan917',
        'mageprince',
        'benjamin-volle',
        'lucafuser',
        'rogerdz',
        'abiverderci',
        'orlangur',
    ];

    /**
     * Community Council committee. Members hold maintainer rights (so they earn
     * maintainer points) but are kept off the public maintainer board unless
     * also on the maintainer roster.
     *
     * @var list<string>
     */
    private array $council = [
        'lfolco',
        'sprankhub',
        'IvanChepurnyi',
        'furan917',
        'rhoerr',
    ];

    public function run(): void
    {
        // Clear the roles this seeder owns first, so logins dropped from the
        // rosters stop being eligible instead of lingering as stale rows. Wrapped
        // in a transaction because EligibilityGate reads this table.
        DB::transaction(function (): void {
            RoleEligibility::query()->whereIn('role', ['maintainer', 'community-council'])->delete();

            foreach ($this->maintainers as $login) {
                RoleEligibility::updateOrCreate(['login' => $login, 'role' => 'maintainer']);
            }

            foreach ($this->council as $login) {
                RoleEligibility::updateOrCreate(['login' => $login, 'role' => 'community-council']);
            }
        });
    }
}
