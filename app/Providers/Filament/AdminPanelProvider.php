<?php
declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Filament Admin Panel Provider
 *
 * Configures the Filament admin panel for the Magento Forger application.
 * This panel provides administrative access to manage users, companies, and view
 * GitHub statistics. Access is restricted to users with the is_admin flag set to true.
 *
 * The panel is accessible at /admin and includes:
 * - User management (CRUD operations)
 * - Company management (CRUD operations)
 * - GitHub statistics dashboard with widgets
 * - User affiliations management
 *
 * @package App\Providers\Filament
 */
class AdminPanelProvider extends PanelProvider
{
    /**
     * Configure the Filament admin panel
     *
     * Sets up the admin panel with the following configuration:
     * - Panel ID: 'admin'
     * - Path: /admin
     * - Authentication: Required (login page enabled)
     * - Brand name: 'Magento Forger'
     * - Primary color: Amber
     * - Auto-discovery of resources, pages, and widgets
     * - Dashboard with account and info widgets
     * - Custom navigation item to return to main site
     * - Standard Laravel middleware stack
     * - Filament authentication middleware
     *
     * Resources are automatically discovered from app/Filament/Resources
     * Pages are automatically discovered from app/Filament/Pages
     * Widgets are automatically discovered from app/Filament/Widgets
     *
     * @param Panel $panel The Filament panel instance to configure
     * @return Panel The configured panel instance
     */
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Magento Forger')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->navigationItems([
                NavigationItem::make('Back to Main Site')
                    ->url('/')
                    ->icon('heroicon-o-arrow-left-circle')
                    ->sort(999),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
