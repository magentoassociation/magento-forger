<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\RoleEligibility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CALLBACK = '/auth/github/callback';

    public function testActiveCouncilMemberIsPromotedToAdminOnLogin(): void
    {
        RoleEligibility::query()->create([
            'login' => 'octocat',
            'role' => 'community-council',
            'active' => true,
        ]);

        $this->mockGitHubUser(id: '123', nickname: 'octocat', email: 'octocat@example.com');

        $this->get(self::CALLBACK)->assertRedirectToRoute('home');

        $user = User::where('github_id', '123')->firstOrFail();
        $this->assertTrue((bool) $user->is_admin);
        $this->assertAuthenticatedAs($user);
    }

    public function testNonCouncilMemberIsNotPromoted(): void
    {
        $this->mockGitHubUser(id: '456', nickname: 'stranger', email: 'stranger@example.com');

        $this->get(self::CALLBACK)->assertRedirectToRoute('home');

        $user = User::where('github_id', '456')->firstOrFail();
        $this->assertFalse((bool) $user->is_admin);
    }

    public function testInactiveCouncilMemberIsNotPromoted(): void
    {
        RoleEligibility::query()->create([
            'login' => 'formercouncil',
            'role' => 'community-council',
            'active' => false,
        ]);

        $this->mockGitHubUser(id: '789', nickname: 'formercouncil', email: 'former@example.com');

        $this->get(self::CALLBACK)->assertRedirectToRoute('home');

        $user = User::where('github_id', '789')->firstOrFail();
        $this->assertFalse((bool) $user->is_admin);
    }

    public function testExistingAdminStatusIsPreservedForNonCouncilMember(): void
    {
        User::factory()->create([
            'github_id' => '999',
            'github_username' => 'boss',
            'is_admin' => true,
        ]);

        $this->mockGitHubUser(id: '999', nickname: 'boss', email: 'boss@example.com');

        $this->get(self::CALLBACK)->assertRedirectToRoute('home');

        $this->assertTrue((bool) User::where('github_id', '999')->firstOrFail()->is_admin);
    }

    private function mockGitHubUser(string $id, string $nickname, ?string $email): void
    {
        $githubUser = Mockery::mock(SocialiteUser::class);
        $githubUser->shouldReceive('getId')->andReturn($id);
        $githubUser->shouldReceive('getName')->andReturn(null);
        $githubUser->shouldReceive('getNickname')->andReturn($nickname);
        $githubUser->shouldReceive('getEmail')->andReturn($email);

        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($githubUser);

        Socialite::shouldReceive('driver')->with('github')->andReturn($provider);
    }
}
