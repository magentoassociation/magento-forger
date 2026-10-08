<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GithubProfile;
use App\Models\LeaderboardEntry;
use App\Models\RoleEligibility;
use App\Services\GitHub\GitHubConnection;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetch display name + avatar for the people shown on the leaderboards (everyone
 * in leaderboard_entries plus the eligibility rosters). Public profile data, so
 * the standard token works. Skips profiles fetched within the freshness window
 * unless --all is passed.
 */
class SyncGitHubProfiles extends Command implements Isolatable
{
    private const STALE_DAYS = 7;

    protected $signature = 'sync:github:profiles {--all : Refetch every profile, ignoring the freshness window}';

    protected $description = 'Fetch GitHub display name + avatar for leaderboard contributors and maintainers.';

    public function handle(GitHubConnection $connection): int
    {
        $logins = LeaderboardEntry::query()->distinct()->pluck('login')
            ->merge(RoleEligibility::query()->distinct()->pluck('login'))
            ->filter()
            ->unique()
            ->values();

        if ($logins->isEmpty()) {
            $this->info('No logins to fetch.');

            return self::SUCCESS;
        }

        $fresh = $this->option('all')
            ? collect()
            : GithubProfile::query()
                ->where('fetched_at', '>=', now()->subDays(self::STALE_DAYS))
                ->pluck('login')
                ->flip();

        $fetched = 0;
        $failed = 0;

        foreach ($logins as $login) {
            if ($fresh->has($login)) {
                continue;
            }

            try {
                $profile = $this->fetchProfile($connection, $login);
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Profile fetch failed', ['login' => $login, 'exception' => $e]);

                continue;
            }

            GithubProfile::updateOrCreate(
                ['login' => $login],
                ['name' => $profile['name'], 'avatar_url' => $profile['avatar_url'], 'fetched_at' => now()],
            );
            $fetched++;
        }

        $this->info("Profiles fetched: {$fetched}, failed: {$failed}.");

        return self::SUCCESS;
    }

    /**
     * @return array{name: string|null, avatar_url: string|null}
     */
    private function fetchProfile(GitHubConnection $connection, string $login): array
    {
        try {
            $response = $connection->rest()->get("users/{$login}");
        } catch (RequestException $e) {
            // A deleted/renamed account 404s — record it (null) so we don't refetch every run.
            if ($e->getResponse() !== null && $e->getResponse()->getStatusCode() === 404) {
                return ['name' => null, 'avatar_url' => null];
            }

            throw $e;
        }

        $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        return [
            'name' => $data['name'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
        ];
    }
}
