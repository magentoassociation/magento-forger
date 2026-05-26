<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class MakeUserAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:make-user-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Makes a user that logged in via GitHub an admin locally';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->ask('User email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Please enter a valid email address.');

            return 1;
        }
        try {
            $user = User::where('email', $email)->firstOrFail();

            if ((bool) $user->is_admin) {
                $this->warn("User {$email} is already an admin. No action taken");

                return SymfonyCommand::SUCCESS;
            }

            // Use direct assignment to bypass mass assignment protection
            $user->is_admin = true;
            $user->save();

            $this->info("User {$email} is now an admin.");

            return 0;
        } catch (ModelNotFoundException) {
            $this->error("User with email {$email} not found.");

            return 1;
        } catch (Exception $e) {
            $this->error("Failed to update user: " . $e->getMessage());

            return 1;
        }
    }
}
