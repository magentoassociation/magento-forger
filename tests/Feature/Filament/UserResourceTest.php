<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
    }

    public function testListShowsGithubIdColumn(): void
    {
        $user = User::factory()->create(['github_id' => '424242', 'github_username' => 'octocat']);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$user])
            ->assertTableColumnExists('github_id')
            ->assertCanRenderTableColumn('github_id');
    }

    public function testResourceHasNoEditPage(): void
    {
        $this->assertArrayNotHasKey('edit', UserResource::getPages());
        $this->assertArrayNotHasKey('create', UserResource::getPages());
    }
}
