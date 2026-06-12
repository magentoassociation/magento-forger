<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Services\Company;

use App\Models\Company;
use App\Models\User;
use App\Services\Company\CompanyMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyMergeServiceTest extends TestCase
{
    use RefreshDatabase;

    private CompanyMergeService $service;

    private Company $source;

    private Company $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CompanyMergeService;

        $this->source = Company::forceCreate([
            'name' => 'Source Corp',
            'email' => 'source@example.test',
            'phone' => '000-000-0001',
            'status' => 'pending',
        ]);

        $this->target = Company::forceCreate([
            'name' => 'Target Corp',
            'email' => 'target@example.test',
            'phone' => '000-000-0002',
            'status' => 'approved',
        ]);
    }

    public function test_affiliations_are_reassigned_to_target(): void
    {
        $user = User::factory()->create();
        $user->affiliations()->create(['company_id' => $this->source->id, 'start_date' => '2024-01-01']);

        $this->service->merge($this->source, $this->target);

        $this->assertDatabaseHas('company_affiliations', [
            'user_id' => $user->id,
            'company_id' => $this->target->id,
        ]);
        $this->assertDatabaseMissing('company_affiliations', [
            'user_id' => $user->id,
            'company_id' => $this->source->id,
        ]);
    }

    public function test_duplicate_affiliations_are_deleted_not_duplicated(): void
    {
        $user = User::factory()->create();
        $user->affiliations()->create(['company_id' => $this->source->id, 'start_date' => '2024-01-01']);
        $user->affiliations()->create(['company_id' => $this->target->id, 'start_date' => '2023-01-01']);

        $this->service->merge($this->source, $this->target);

        $count = \App\Models\CompanyAffiliation::where('user_id', $user->id)
            ->where('company_id', $this->target->id)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_non_overlapping_users_are_all_moved(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $existingUser = User::factory()->create();

        $userA->affiliations()->create(['company_id' => $this->source->id, 'start_date' => '2024-01-01']);
        $userB->affiliations()->create(['company_id' => $this->source->id, 'start_date' => '2024-01-01']);
        $existingUser->affiliations()->create(['company_id' => $this->target->id, 'start_date' => '2023-01-01']);

        $this->service->merge($this->source, $this->target);

        $this->assertDatabaseHas('company_affiliations', ['user_id' => $userA->id, 'company_id' => $this->target->id]);
        $this->assertDatabaseHas('company_affiliations', ['user_id' => $userB->id, 'company_id' => $this->target->id]);
        $this->assertDatabaseHas('company_affiliations', ['user_id' => $existingUser->id, 'company_id' => $this->target->id]);
    }

    public function test_source_is_marked_rejected_after_merge(): void
    {
        $this->service->merge($this->source, $this->target);

        $this->assertSame('rejected', $this->source->fresh()->status);
    }

    public function test_target_is_unchanged_after_merge(): void
    {
        $this->service->merge($this->source, $this->target);

        $this->assertSame('approved', $this->target->fresh()->status);
    }

    public function test_merge_with_no_affiliations_still_rejects_source(): void
    {
        $this->service->merge($this->source, $this->target);

        $this->assertSame('rejected', $this->source->fresh()->status);
        $this->assertSame(0, $this->target->affiliations()->count());
    }

    public function test_only_duplicate_affiliations_are_deleted_unique_ones_move(): void
    {
        $shared = User::factory()->create();
        $unique = User::factory()->create();

        $shared->affiliations()->create(['company_id' => $this->source->id, 'start_date' => '2024-01-01']);
        $unique->affiliations()->create(['company_id' => $this->source->id, 'start_date' => '2024-01-01']);
        $shared->affiliations()->create(['company_id' => $this->target->id, 'start_date' => '2023-01-01']);

        $this->service->merge($this->source, $this->target);

        $this->assertDatabaseHas('company_affiliations', ['user_id' => $unique->id, 'company_id' => $this->target->id]);
        $targetAffiliationCount = $this->target->affiliations()->count();
        $this->assertSame(2, $targetAffiliationCount);
    }
}
