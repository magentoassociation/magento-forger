<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RoleEligibility;
use App\Services\GitHub\GitHubConnection;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sync the maintainer and community-council team rosters from GitHub into
 * role_eligibilities, which gates who can earn maintainer points. Requires a
 * token with org Members:read.
 */
class SyncGitHubTeams extends Command implements Isolatable
{
    protected $signature = 'sync:github:teams';

    protected $description = 'Sync the maintainer and community-council team rosters into role_eligibilities.';

    public function handle(GitHubConnection $connection): int
    {
        $owner = explode('/', (string) config('github.repo'))[0] ?? '';

        if ($owner === '') {
            $this->error('Missing or invalid repository. Set it in config/github.php');

            return 1;
        }

        $teams = [
            'maintainer' => (string) config('leaderboard.teams.maintainers'),
            'community-council' => (string) config('leaderboard.teams.council'),
        ];

        $hadError = false;

        foreach ($teams as $role => $slug) {
            if ($slug === '') {
                $this->warn("No team slug configured for {$role}; skipping.");

                continue;
            }

            try {
                $logins = $this->fetchMembers($connection, $owner, $slug);
            } catch (Throwable $e) {
                $hadError = true;
                $this->error("Failed to fetch team {$owner}/{$slug}: ".$this->describe($e));
                Log::error('Team roster sync failed', ['team' => $slug, 'exception' => $e]);

                continue;
            }

            DB::transaction(function () use ($role, $logins): void {
                RoleEligibility::query()->where('role', $role)->delete();

                foreach (array_chunk($logins, 500) as $chunk) {
                    RoleEligibility::query()->insert(array_map(
                        fn (string $login): array => [
                            'login' => $login,
                            'role' => $role,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        $chunk,
                    ));
                }
            });

            $this->info("{$role}: {$slug} → ".count($logins).' members synced.');
        }

        if ($hadError) {
            $this->warn('One or more teams could not be synced; existing rosters for those roles were left unchanged.');

            return 1;
        }

        $this->info('Team rosters synced.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function fetchMembers(GitHubConnection $connection, string $owner, string $slug): array
    {
        $logins = [];
        $page = 1;

        do {
            $response = $connection->rest()->get("orgs/{$owner}/teams/{$slug}/members", [
                'query' => ['per_page' => 100, 'page' => $page],
            ]);
            $members = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            $memberCount = count($members);

            foreach ($members as $member) {
                if (! empty($member['login'])) {
                    $logins[] = $member['login'];
                }
            }

            $page++;
        } while ($memberCount === 100);

        return array_values(array_unique($logins));
    }

    private function describe(Throwable $e): string
    {
        if ($e instanceof RequestException && $e->getResponse() !== null) {
            $status = $e->getResponse()->getStatusCode();

            return match ($status) {
                404 => "404 — the token can't see this team's members. GitHub returns 404 (not 403) "
                    ."when the token lacks `read:org`, its user isn't a member of the org, or the team is "
                    ."secret and the user isn't on it. Verify the slug, and that GITHUB_TOKEN has "
                    .'read:org for this org.',
                403 => '403 — forbidden (missing scope or rate limited).',
                401 => '401 — the token is invalid or expired.',
                default => "HTTP {$status}.",
            };
        }

        return $e->getMessage();
    }
}
