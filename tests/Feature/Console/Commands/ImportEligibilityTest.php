<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Models\RoleEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_roles_and_replaces_existing_roster(): void
    {
        RoleEligibility::create(['login' => 'old', 'role' => 'contributor']);

        $path = tempnam(sys_get_temp_dir(), 'elig');
        file_put_contents($path, "login,role\njane,contributor\nmod,maintainer\njane,maintainer\ncouncilor,community-council\nbad,reviewer\n");

        $this->artisan('leaderboard:import-eligibility', ['path' => $path])->assertExitCode(0);
        @unlink($path);

        $this->assertDatabaseHas('role_eligibilities', ['login' => 'jane', 'role' => 'contributor']);
        $this->assertDatabaseHas('role_eligibilities', ['login' => 'mod', 'role' => 'maintainer']);
        $this->assertDatabaseHas('role_eligibilities', ['login' => 'jane', 'role' => 'maintainer']);
        $this->assertDatabaseHas('role_eligibilities', ['login' => 'councilor', 'role' => 'community-council']);
        $this->assertDatabaseMissing('role_eligibilities', ['login' => 'old']);   // contributor roster replaced
        $this->assertDatabaseMissing('role_eligibilities', ['login' => 'bad']);   // invalid role skipped
    }

    public function test_missing_file_errors(): void
    {
        $this->artisan('leaderboard:import-eligibility', ['path' => '/no/such/file.csv'])->assertExitCode(1);
    }
}
