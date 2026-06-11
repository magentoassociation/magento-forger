<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Services\Employment;

use App\Models\Company;
use App\Models\User;
use App\Services\Employment\EmploymentConflictDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmploymentConflictDetectorTest extends TestCase
{
    use RefreshDatabase;

    private EmploymentConflictDetector $detector;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = new EmploymentConflictDetector;
        $this->user = User::factory()->create();
        $this->company = Company::forceCreate([
            'name' => 'Test Corp',
            'email' => 'test@testcorp.example',
            'phone' => '000-000-0001',
            'status' => 'approved',
        ]);
    }

    public function test_no_conflict_when_no_affiliations_exist(): void
    {
        $result = $this->detector->hasConflict(
            $this->user->affiliations(),
            $this->company->id,
            '2024-01-01',
            '2024-06-30',
        );

        $this->assertFalse($result);
    }

    public function test_conflict_when_ranges_overlap(): void
    {
        $this->user->affiliations()->create([
            'company_id' => $this->company->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $result = $this->detector->hasConflict(
            $this->user->affiliations(),
            $this->company->id,
            '2024-06-01',
            '2024-09-30',
        );

        $this->assertTrue($result);
    }

    public function test_no_conflict_when_ranges_do_not_overlap(): void
    {
        $this->user->affiliations()->create([
            'company_id' => $this->company->id,
            'start_date' => '2023-01-01',
            'end_date' => '2023-12-31',
        ]);

        $result = $this->detector->hasConflict(
            $this->user->affiliations(),
            $this->company->id,
            '2024-01-01',
            '2024-12-31',
        );

        $this->assertFalse($result);
    }

    public function test_conflict_when_existing_affiliation_has_open_end_date(): void
    {
        $this->user->affiliations()->create([
            'company_id' => $this->company->id,
            'start_date' => '2024-01-01',
            'end_date' => null,
        ]);

        $result = $this->detector->hasConflict(
            $this->user->affiliations(),
            $this->company->id,
            '2025-01-01',
            '2025-06-30',
        );

        $this->assertTrue($result);
    }

    public function test_exclude_id_skips_own_affiliation_on_update(): void
    {
        $affiliation = $this->user->affiliations()->create([
            'company_id' => $this->company->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $result = $this->detector->hasConflict(
            $this->user->affiliations(),
            $this->company->id,
            '2024-01-01',
            '2024-12-31',
            $affiliation->id,
        );

        $this->assertFalse($result);
    }

    public function test_conflict_detected_for_different_affiliation_even_when_exclude_id_set(): void
    {
        $this->user->affiliations()->create([
            'company_id' => $this->company->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $other = $this->user->affiliations()->create([
            'company_id' => $this->company->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);

        $result = $this->detector->hasConflict(
            $this->user->affiliations(),
            $this->company->id,
            '2024-06-01',
            '2024-09-30',
            $other->id,
        );

        $this->assertTrue($result);
    }

    public function test_no_conflict_for_different_company(): void
    {
        $other = Company::forceCreate([
            'name' => 'Other Corp',
            'email' => 'other@othercorp.example',
            'phone' => '000-000-0002',
            'status' => 'approved',
        ]);

        $this->user->affiliations()->create([
            'company_id' => $other->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $result = $this->detector->hasConflict(
            $this->user->affiliations(),
            $this->company->id,
            '2024-06-01',
            '2024-09-30',
        );

        $this->assertFalse($result);
    }
}
