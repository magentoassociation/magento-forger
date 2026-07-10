<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Models\RoleEligibility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoteCouncilAdminsTest extends TestCase
{
    use RefreshDatabase;

    public function testPromotesActiveCouncilMembersOnly(): void
    {
        RoleEligibility::create(['login' => 'councilor', 'role' => 'community-council', 'active' => true]);
        RoleEligibility::create(['login' => 'former', 'role' => 'community-council', 'active' => false]);
        RoleEligibility::create(['login' => 'maint', 'role' => 'maintainer', 'active' => true]);

        $councilor = User::factory()->create(['github_username' => 'councilor', 'is_admin' => false]);
        $former = User::factory()->create(['github_username' => 'former', 'is_admin' => false]);
        $maintainer = User::factory()->create(['github_username' => 'maint', 'is_admin' => false]);

        $this->artisan('app:promote-council-admins')->assertExitCode(0);

        $this->assertTrue((bool) $councilor->refresh()->is_admin);
        $this->assertFalse((bool) $former->refresh()->is_admin);
        $this->assertFalse((bool) $maintainer->refresh()->is_admin);
    }

    public function testWarnsWhenNoActiveCouncilMembers(): void
    {
        $this->artisan('app:promote-council-admins')
            ->expectsOutputToContain('No active community-council members found')
            ->assertExitCode(0);
    }
}
