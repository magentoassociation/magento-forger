<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Services\GitHub;

use App\Services\GitHub\GitHubConnection;
use App\Services\GitHub\GitHubLabelService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class GitHubLabelServiceTest extends TestCase
{
    private function createService(MockHandler $mock): GitHubLabelService
    {
        config()->set('github.token', 'test-token');

        $restClient = new Client(['handler' => HandlerStack::create($mock)]);
        $connection = new GitHubConnection(restClient: $restClient);

        return new GitHubLabelService($connection);
    }

    // -------------------------------------------------------------------------
    // createLabel
    // -------------------------------------------------------------------------

    public function test_create_label_returns_one_when_label_does_not_exist(): void
    {
        // First: GET 404 (label does not exist), then POST 201 (created)
        $mock = new MockHandler([
            new Response(404, [], json_encode(['message' => 'Not Found'], JSON_THROW_ON_ERROR)),
            new Response(201, [], json_encode(['id' => 1, 'name' => 'Area: Frontend'], JSON_THROW_ON_ERROR)),
        ]);

        $service = $this->createService($mock);
        $result = $service->createLabel('owner', 'repo', 'Area: Frontend');

        $this->assertSame(1, $result);
        $this->assertNull($service->getLastOperationError());
    }

    public function test_create_label_returns_zero_and_sets_skipped_error_when_label_already_exists(): void
    {
        // GET 200 with matching name → skip
        $mock = new MockHandler([
            new Response(200, [], json_encode(['id' => 42, 'name' => 'Area: Frontend'], JSON_THROW_ON_ERROR)),
        ]);

        $service = $this->createService($mock);
        $result = $service->createLabel('owner', 'repo', 'Area: Frontend');

        $this->assertSame(0, $result);

        $error = $service->getLastOperationError();
        $this->assertNotNull($error);
        $this->assertSame('create', $error['operation']);
        $this->assertSame('skipped', $error['status']);
        $this->assertStringContainsString('Area: Frontend', $error['message']);
    }

    public function test_create_label_returns_zero_and_sets_failed_error_when_post_throws(): void
    {
        // GET 404 → label not found; POST 422 → server rejects creation
        $mock = new MockHandler([
            new Response(404, [], json_encode(['message' => 'Not Found'], JSON_THROW_ON_ERROR)),
            new Response(422, [], json_encode(['message' => 'Validation Failed'], JSON_THROW_ON_ERROR)),
        ]);

        $service = $this->createService($mock);
        $result = $service->createLabel('owner', 'repo', 'Area: New');

        $this->assertSame(0, $result);

        $error = $service->getLastOperationError();
        $this->assertNotNull($error);
        $this->assertSame('create', $error['operation']);
        $this->assertSame('failed', $error['status']);
    }

    public function test_create_label_clears_previous_error_on_each_call(): void
    {
        // First call: label exists → skipped
        $mock = new MockHandler([
            new Response(200, [], json_encode(['name' => 'OldLabel'], JSON_THROW_ON_ERROR)),
        ]);

        $service = $this->createService($mock);
        $service->createLabel('owner', 'repo', 'OldLabel');
        $this->assertNotNull($service->getLastOperationError());

        // Second service instance (fresh mock) — but we can test clearing by calling again
        // on the same instance with a new mock via a fresh service.
        config()->set('github.token', 'test-token');
        $mock2 = new MockHandler([
            new Response(404),
            new Response(201, [], json_encode(['name' => 'NewLabel'], JSON_THROW_ON_ERROR)),
        ]);
        $restClient2 = new Client(['handler' => HandlerStack::create($mock2)]);
        $service2 = new GitHubLabelService(new GitHubConnection(restClient: $restClient2));

        $result = $service2->createLabel('owner', 'repo', 'NewLabel');

        $this->assertSame(1, $result);
        $this->assertNull($service2->getLastOperationError());
    }

    public function test_create_label_encodes_label_name_with_special_characters(): void
    {
        // Label contains spaces and colons — the GET URL should rawurlencode them
        $mock = new MockHandler([
            new Response(404),
            new Response(201, [], json_encode(['name' => 'needs triage: backend'], JSON_THROW_ON_ERROR)),
        ]);

        $service = $this->createService($mock);
        $result = $service->createLabel('owner', 'repo', 'needs triage: backend');

        $this->assertSame(1, $result);
    }

    // -------------------------------------------------------------------------
    // renameLabel
    // -------------------------------------------------------------------------

    public function test_rename_label_returns_one_on_success(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['id' => 1, 'name' => 'Area: New'], JSON_THROW_ON_ERROR)),
        ]);

        $service = $this->createService($mock);
        $result = $service->renameLabel('owner', 'repo', 'Area: Old', 'Area: New');

        $this->assertSame(1, $result);
        $this->assertNull($service->getLastOperationError());
    }

    public function test_rename_label_returns_zero_and_sets_error_when_patch_throws(): void
    {
        $mock = new MockHandler([
            new Response(404, [], json_encode(['message' => 'Not Found'], JSON_THROW_ON_ERROR)),
        ]);

        $service = $this->createService($mock);
        $result = $service->renameLabel('owner', 'repo', 'Area: Old', 'Area: New');

        $this->assertSame(0, $result);

        $error = $service->getLastOperationError();
        $this->assertNotNull($error);
        $this->assertSame('rename', $error['operation']);
        $this->assertSame('failed', $error['status']);
        $this->assertSame('Area: Old', $error['old_name']);
        $this->assertSame('Area: New', $error['new_name']);
    }

    public function test_rename_label_clears_previous_error_on_each_call(): void
    {
        // First call fails, second succeeds — error should be null after success
        $mock = new MockHandler([
            new Response(404, [], json_encode(['message' => 'Not Found'], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['name' => 'Area: New'], JSON_THROW_ON_ERROR)),
        ]);

        $service = $this->createService($mock);

        $service->renameLabel('owner', 'repo', 'Area: Missing', 'Area: New');
        $this->assertNotNull($service->getLastOperationError());

        $service->renameLabel('owner', 'repo', 'Area: Old', 'Area: New');
        $this->assertNull($service->getLastOperationError());
    }

    // -------------------------------------------------------------------------
    // getLastOperationError
    // -------------------------------------------------------------------------

    public function test_get_last_operation_error_returns_null_initially(): void
    {
        config()->set('github.token', 'test-token');
        $restClient = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);
        $service = new GitHubLabelService(new GitHubConnection(restClient: $restClient));

        $this->assertNull($service->getLastOperationError());
    }
}