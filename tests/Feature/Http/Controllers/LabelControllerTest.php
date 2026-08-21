<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use OpenSearch\Client;
use Tests\TestCase;

class LabelControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function testListAllLabelsGroupsLabelsByPrefixAndSortsPrefixes(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->once()->with(Mockery::on(static function (array $params): bool {
            return $params['index'] === 'github-issues'
                && $params['body']['query']['term']['is_open'] === true
                && $params['body']['aggs']['by_label']['terms']['field'] === 'labels.keyword';
        }))->andReturn([
            'aggregations' => [
                'by_label' => [
                    'buckets' => [
                        ['key' => 'Standalone', 'doc_count' => 1],
                        ['key' => 'Component: Checkout', 'doc_count' => 5],
                        ['key' => 'Area: Frontend', 'doc_count' => 3],
                    ],
                ],
            ],
        ]);
        $this->app->instance(Client::class, $client);

        $adminUser = $this->createUser(true);

        $response = $this->actingAs($adminUser)->get(route('labels.listAllLabels'));

        $response->assertOk();
        $response->assertViewIs('labels.allLabels');

        $labels = $response->viewData('labels');

        $this->assertTrue((static function (array $labels): bool {
            return array_keys($labels) === ['Area', 'Component', 'no_prefix']
                && $labels['Area'][0] === ['label' => 'Area: Frontend', 'count' => 3]
                && $labels['Component'][0] === ['label' => 'Component: Checkout', 'count' => 5]
                && $labels['no_prefix'][0] === ['label' => 'Standalone', 'count' => 1];
        })($labels));
    }

    private function createUser(bool $isAdmin): User
    {
        return User::factory()->create(['is_admin' => $isAdmin]);
    }
}
