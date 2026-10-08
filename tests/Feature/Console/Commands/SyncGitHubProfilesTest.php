<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Models\LeaderboardEntry;
use App\Services\GitHub\GitHubConnection;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncGitHubProfilesTest extends TestCase
{
    use RefreshDatabase;

    public function testFetchesAndStoresNameAndAvatar(): void
    {
        LeaderboardEntry::create([
            'login' => 'janedoe',
            'board' => 'contributor',
            'window' => 'rolling12',
            'score' => 5.0,
            'computed_at' => now(),
        ]);

        $mock = new MockHandler([
            new Response(200, [], json_encode(['name' => 'Jane Doe', 'avatar_url' => 'https://example.com/jane.png'])),
        ]);
        $rest = new Client(['handler' => HandlerStack::create($mock)]);
        $this->app->instance(
            GitHubConnection::class,
            new GitHubConnection(graphQlClient: new Client, restClient: $rest),
        );

        $this->artisan('sync:github:profiles')->assertExitCode(0);

        $this->assertDatabaseHas('github_profiles', [
            'login' => 'janedoe',
            'name' => 'Jane Doe',
            'avatar_url' => 'https://example.com/jane.png',
        ]);
    }
}
