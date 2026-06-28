<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Database\Seeders;

use App\Models\RoleEligibility;
use Database\Seeders\RoleEligibilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleEligibilitySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_removes_stale_rows_and_keeps_current_roster(): void
    {
        // A maintainer who has since left the roster.
        RoleEligibility::create(['login' => 'departed', 'role' => 'maintainer']);

        (new RoleEligibilitySeeder)->run();

        $this->assertDatabaseMissing('role_eligibilities', ['login' => 'departed', 'role' => 'maintainer']);
        $this->assertDatabaseHas('role_eligibilities', ['login' => 'lfolco', 'role' => 'maintainer']);
        $this->assertDatabaseHas('role_eligibilities', ['login' => 'lfolco', 'role' => 'community-council']);
    }

    public function test_is_idempotent(): void
    {
        $seeder = new RoleEligibilitySeeder;
        $seeder->run();
        $countAfterFirst = RoleEligibility::count();

        $seeder->run();

        $this->assertSame($countAfterFirst, RoleEligibility::count());
    }

    public function test_does_not_touch_other_roles(): void
    {
        RoleEligibility::create(['login' => 'someone', 'role' => 'contributor']);

        (new RoleEligibilitySeeder)->run();

        $this->assertDatabaseHas('role_eligibilities', ['login' => 'someone', 'role' => 'contributor']);
    }
}
