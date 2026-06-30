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

    public function testCreateLabelReturnsOneWhenLabelDoesNotExist(): void
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

    public function testCreateLabelReturnsZeroAndSetsSkippedErrorWhenLabelAlreadyExists(): void
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

    public function testCreateLabelReturnsZeroAndSetsFailedErrorWhenPostThrows(): void
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

    public function testCreateLabelClearsPreviousErrorOnEachCall(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['name' => 'OldLabel'], JSON_THROW_ON_ERROR)),
            new Response(404),
            new Response(201, [], json_encode(['name' => 'NewLabel'], JSON_THROW_ON_ERROR)),
        ]);

        $service = $this->createService($mock);

        $service->createLabel('owner', 'repo', 'OldLabel');
        $this->assertNotNull($service->getLastOperationError());

        $result = $service->createLabel('owner', 'repo', 'NewLabel');
        $this->assertSame(1, $result);
        $this->assertNull($service->getLastOperationError());
    }

    public function testCreateLabelEncodesLabelNameWithSpecialCharacters(): void
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

    public function testRenameLabelReturnsOneOnSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['id' => 1, 'name' => 'Area: New'], JSON_THROW_ON_ERROR)),
        ]);

        $service = $this->createService($mock);
        $result = $service->renameLabel('owner', 'repo', 'Area: Old', 'Area: New');

        $this->assertSame(1, $result);
        $this->assertNull($service->getLastOperationError());
    }

    public function testRenameLabelReturnsZeroAndSetsErrorWhenPatchThrows(): void
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

    public function testRenameLabelClearsPreviousErrorOnEachCall(): void
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

    public function testGetLastOperationErrorReturnsNullInitially(): void
    {
        config()->set('github.token', 'test-token');
        $restClient = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);
        $service = new GitHubLabelService(new GitHubConnection(restClient: $restClient));

        $this->assertNull($service->getLastOperationError());
    }
}
