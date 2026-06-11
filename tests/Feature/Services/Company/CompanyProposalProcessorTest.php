<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Services\Company;

use App\Models\Company;
use App\Services\Company\CompanyProposalProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyProposalProcessorTest extends TestCase
{
    use RefreshDatabase;

    private CompanyProposalProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new CompanyProposalProcessor;
    }

    private function validData(array $overrides = []): array
    {
        return array_merge(['name' => 'Acme Corp'], $overrides);
    }

    public function test_creates_pending_company_and_returns_success(): void
    {
        $result = $this->processor->process($this->validData());

        $this->assertSame(200, $result['httpStatus']);
        $this->assertTrue($result['body']['success']);
        $this->assertSame('Acme Corp', $result['body']['company']['name']);
        $this->assertSame('pending', $result['body']['company']['status']);
        $this->assertDatabaseHas(Company::class, ['name' => 'Acme Corp', 'status' => 'pending']);
    }

    public function test_returns_422_when_name_is_too_short_after_sanitization(): void
    {
        $result = $this->processor->process($this->validData(['name' => 'A']));

        $this->assertSame(422, $result['httpStatus']);
        $this->assertFalse($result['body']['success']);
        $this->assertSame('Company name is too short.', $result['body']['message']);
    }

    public function test_returns_warning_when_pending_duplicate_exists(): void
    {
        Company::forceCreate([
            'name' => 'Acme Corp',
            'email' => 'acme@example.test',
            'phone' => '000-000-9001',
            'status' => 'pending',
        ]);

        $result = $this->processor->process($this->validData());

        $this->assertSame(200, $result['httpStatus']);
        $this->assertTrue($result['body']['success']);
        $this->assertTrue($result['body']['warning']);
        $this->assertSame('pending', $result['body']['company']['status']);
    }

    public function test_returns_422_when_approved_duplicate_exists(): void
    {
        Company::forceCreate([
            'name' => 'Acme Corp',
            'email' => 'acme@example.test',
            'phone' => '000-000-9001',
            'status' => 'approved',
        ]);

        $result = $this->processor->process($this->validData());

        $this->assertSame(422, $result['httpStatus']);
        $this->assertFalse($result['body']['success']);
        $this->assertStringContainsString('already approved', $result['body']['message']);
    }

    public function test_returns_422_when_rejected_duplicate_exists(): void
    {
        Company::forceCreate([
            'name' => 'Acme Corp',
            'email' => 'acme@example.test',
            'phone' => '000-000-9001',
            'status' => 'rejected',
        ]);

        $result = $this->processor->process($this->validData());

        $this->assertSame(422, $result['httpStatus']);
        $this->assertFalse($result['body']['success']);
        $this->assertSame('This company already exists.', $result['body']['message']);
    }

    public function test_case_insensitive_duplicate_detection(): void
    {
        Company::forceCreate([
            'name' => 'ACME CORP',
            'email' => 'acme@example.test',
            'phone' => '000-000-9001',
            'status' => 'approved',
        ]);

        $result = $this->processor->process($this->validData(['name' => 'acme corp']));

        $this->assertSame(422, $result['httpStatus']);
        $this->assertFalse($result['body']['success']);
    }

    public function test_uses_provided_website_when_not_a_placeholder(): void
    {
        $result = $this->processor->process($this->validData(['website' => 'https://acme.com']));

        $this->assertSame(200, $result['httpStatus']);
        $this->assertDatabaseHas(Company::class, ['name' => 'Acme Corp', 'website' => 'https://acme.com']);
    }

    public function test_ignores_common_website_placeholders(): void
    {
        $result = $this->processor->process($this->validData(['website' => 'https://example.com']));

        $this->assertSame(200, $result['httpStatus']);
        $company = Company::where('name', 'Acme Corp')->first();
        $this->assertStringStartsWith('https://pending.example.com/', $company->website);
    }
}
