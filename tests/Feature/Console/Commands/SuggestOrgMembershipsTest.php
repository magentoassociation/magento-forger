<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\SuggestOrgMemberships;
use App\Models\Organization;
use App\Models\UserOrgMembership;
use App\Services\Leaderboard\AuthorCompanyReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestOrgMembershipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_profile_memberships_and_respects_bots_and_manual(): void
    {
        $manualOrg = Organization::create(['name' => 'Manual Co', 'slug' => 'manual-co', 'type' => 'agency']);
        UserOrgMembership::create([
            'login' => 'kim',
            'organization_id' => $manualOrg->id,
            'from_date' => null,
            'to_date' => null,
            'source' => 'manual',
            'confidence' => 100,
        ]);

        $this->mock(AuthorCompanyReader::class)
            ->shouldReceive('read')
            ->andReturn([
                'jane' => '@Acme',
                'bob' => 'Acme',
                'kim' => 'Other Co',     // has a manual membership → skipped
                'engcom-ci' => 'Adobe',  // bot → skipped
            ]);

        $this->artisan('leaderboard:suggest-memberships')->assertExitCode(0);

        $acme = Organization::where('slug', 'acme')->first();
        $this->assertNotNull($acme);

        $this->assertDatabaseHas('user_org_memberships', ['login' => 'jane', 'organization_id' => $acme->id, 'source' => 'profile']);
        $this->assertDatabaseHas('user_org_memberships', ['login' => 'bob', 'organization_id' => $acme->id, 'source' => 'profile']);

        $this->assertDatabaseHas('user_org_memberships', ['login' => 'kim', 'source' => 'manual']);
        $this->assertDatabaseMissing('user_org_memberships', ['login' => 'kim', 'source' => 'profile']);

        $this->assertDatabaseMissing('user_org_memberships', ['login' => 'engcom-ci']);
    }

    public function test_is_idempotent(): void
    {
        $this->mock(AuthorCompanyReader::class)
            ->shouldReceive('read')
            ->andReturn(['jane' => 'Acme']);

        $this->artisan('leaderboard:suggest-memberships')->assertExitCode(0);
        $this->artisan('leaderboard:suggest-memberships')->assertExitCode(0);

        $this->assertSame(1, UserOrgMembership::where('login', 'jane')->where('source', 'profile')->count());
    }

    public function test_clears_stale_profile_suggestion_when_login_now_skipped(): void
    {
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme', 'type' => 'agency']);
        UserOrgMembership::create([
            'login' => 'jane',
            'organization_id' => $org->id,
            'from_date' => null,
            'to_date' => null,
            'source' => 'profile',
            'confidence' => 30,
        ]);

        // Jane still appears in the reader, but her company is now blank → skipped.
        $this->mock(AuthorCompanyReader::class)
            ->shouldReceive('read')
            ->andReturn(['jane' => '']);

        $this->artisan('leaderboard:suggest-memberships')->assertExitCode(0);

        $this->assertDatabaseMissing('user_org_memberships', ['login' => 'jane', 'source' => 'profile']);
    }

    public function test_clears_stale_profile_suggestion_when_login_drops_out_of_reader(): void
    {
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme', 'type' => 'agency']);
        UserOrgMembership::create([
            'login' => 'jane',
            'organization_id' => $org->id,
            'from_date' => null,
            'to_date' => null,
            'source' => 'profile',
            'confidence' => 30,
        ]);

        // Jane no longer appears in the reader at all (e.g. emptied her company).
        $this->mock(AuthorCompanyReader::class)
            ->shouldReceive('read')
            ->andReturn(['bob' => 'Acme']);

        $this->artisan('leaderboard:suggest-memberships')->assertExitCode(0);

        $this->assertDatabaseMissing('user_org_memberships', ['login' => 'jane', 'source' => 'profile']);
        $this->assertDatabaseHas('user_org_memberships', ['login' => 'bob', 'source' => 'profile']);
    }

    public function test_does_not_touch_manual_memberships_on_rebuild(): void
    {
        $org = Organization::create(['name' => 'Manual Co', 'slug' => 'manual-co', 'type' => 'agency']);
        UserOrgMembership::create([
            'login' => 'kim',
            'organization_id' => $org->id,
            'from_date' => null,
            'to_date' => null,
            'source' => 'manual',
            'confidence' => 100,
        ]);

        $this->mock(AuthorCompanyReader::class)
            ->shouldReceive('read')
            ->andReturn(['jane' => 'Acme']);

        $this->artisan('leaderboard:suggest-memberships')->assertExitCode(0);

        $this->assertDatabaseHas('user_org_memberships', ['login' => 'kim', 'source' => 'manual']);
    }

    public function test_normalizes_company_names(): void
    {
        $this->assertSame('Acme', SuggestOrgMemberships::normalizeCompanyName('@Acme'));
        $this->assertSame('Acme Inc', SuggestOrgMemberships::normalizeCompanyName('  Acme   Inc '));
        $this->assertSame('', SuggestOrgMemberships::normalizeCompanyName('@'));
    }
}
