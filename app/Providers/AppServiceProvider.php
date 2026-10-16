<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Providers;

use App\Menus\MainMenu;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Guests are denied: the closure needs a User.
        Gate::define('view-community-picked', fn (User $user): bool => $user->canViewCommunityPicked());

        // Only the layout renders the menu; '*' would recurse, as the menu renders the caret partial.
        view()->composer('layouts.app', function ($view) {
            $view->with('mainMenu', MainMenu::build());
        });
    }
}
