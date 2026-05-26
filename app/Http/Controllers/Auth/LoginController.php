<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class LoginController
{
    /**
     * Redirect the user to GitHub for authentication.
     *
     * @return \Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirectToGitHub()
    {
        return Socialite::driver('github')->redirect();
    }

    /**
     * Handle GitHub callback after authentication
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleGitHubCallback()
    {
        try {
            $githubUser = Socialite::driver('github')->user();

            // GitHub returns private emails by default with user:email scope
            // Only fall back if GitHub truly has no verified email (very rare)
            $email = $githubUser->getEmail();

            if (!$email) {
                // Use a unique, non-conflicting fallback for the rare case
                // where a GitHub user has zero verified emails
                $email = $githubUser->getId() . '@github.noreply.local';

                Log::warning('GitHub user has no verified email', [
                    'github_id' => $githubUser->getId(),
                    'github_username' => $githubUser->getNickname()
                ]);
            }

            $user = User::updateOrCreate(
                ['github_id' => $githubUser->getId()],
                [
                    'name' => $githubUser->getName() ?? $githubUser->getNickname(),
                    'email' => $email,
                    'github_username' => $githubUser->getNickname(),
                    'github_id' => $githubUser->getId()
                ]
            );

            Auth::login($user);

            return redirect()->route('home');

        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            Log::warning('Invalid OAuth state', ['error' => $e->getMessage()]);

            return redirect()->route('home')
                ->withErrors(['error' => 'Authentication failed. Please try again.']);

        } catch (\Exception $e) {
            Log::error('GitHub OAuth failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('home')
                ->withErrors(['error' => 'Authentication failed. Please try again.']);
        } catch (\Throwable $e) {
            Log::error('GitHub OAuth error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('home')
                ->withErrors(['error' => 'Unable to authenticate with GitHub. Please try again.']);
        }
    }
}
