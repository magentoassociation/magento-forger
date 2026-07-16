<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RoleEligibility;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Backfill admin rights for community-council members who already exist as
 * users. New logins are promoted in the GitHub callback; this covers council
 * members who logged in before that logic existed, or who were added to the
 * council after their last login.
 *
 * Grants only — never revokes admins granted by other means.
 */
class PromoteCouncilAdmins extends Command
{
    protected $signature = 'app:promote-council-admins';

    protected $description = 'Grant admin rights to existing users who are active community-council members.';

    public function handle(): int
    {
        $logins = RoleEligibility::query()
            ->where('role', 'community-council')
            ->where('active', true)
            ->pluck('login');

        if ($logins->isEmpty()) {
            $this->warn('No active community-council members found. Run sync:github:teams first.');

            return self::SUCCESS;
        }

        $promoted = 0;

        User::query()
            ->whereIn('github_username', $logins)
            ->where('is_admin', false)
            ->each(function (User $user) use (&$promoted): void {
                $user->is_admin = true;
                $user->save();
                $this->line("Promoted {$user->github_username} ({$user->email}).");
                $promoted++;
            });

        $this->info("<fg=green>{$promoted} council member(s) promoted to admin.</>");

        return self::SUCCESS;
    }
}
