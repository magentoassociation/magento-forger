<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Menus;

use App\Helpers\RouteLabelHelper;
use Illuminate\Support\Facades\Route;
use Spatie\Menu\Laravel\Html;
use Spatie\Menu\Laravel\Link;
use Spatie\Menu\Laravel\Menu;

class MainMenu
{
    // All nav links temporarily hidden — only the logo shows in the navbar.
    // Restore the pattern below to bring the menu back.
    private const MENU_ROUTE_PATTERN = '/^(home|leaderboard\.index|issues|prs|labels|employment)(\.[\w]+)?$/';

    public static function build(): Menu
    {
        $currentRoute = Route::currentRouteName();

        $routes = collect(Route::getRoutes())
            ->filter(fn (\Illuminate\Routing\Route $route): bool => self::hasNoRequiredParameters($route))
            ->filter(fn (\Illuminate\Routing\Route $route): bool => in_array('GET', $route->methods(), true))
            ->filter(function (\Illuminate\Routing\Route $route): bool {
                $adminOnlyRoutes = [
                    'leaderboard.index',
                ];
                $name = $route->getName();
                if (in_array($name, $adminOnlyRoutes, true)) {
                    return auth()->check() && auth()->user()->is_admin == 1;
                }

                return true;
            })
            ->map(fn ($route) => $route->getName())
            ->filter(fn ($name) => preg_match(self::MENU_ROUTE_PATTERN, $name));

        $menu = Menu::new()
            ->addClass('navbar-nav me-auto mb-2 mb-lg-0')
            ->setActiveClassOnLink()
            ->setActiveFromRequest();

        $grouped = $routes->groupBy(fn (string $name): string => explode('.', $name)[0]);

        foreach ($grouped as $mainItem => $subRoutes) {
            $landingRoute = $subRoutes->first(fn (string $name): bool => $name === $mainItem || $name === "{$mainItem}.index");
            $childRoutes = $subRoutes->filter(fn (string $name): bool => $name !== $landingRoute);

            if ($landingRoute && $childRoutes->isEmpty()) {
                $menu->add(
                    Link::toRoute($landingRoute, self::formatLabel($landingRoute))
                        ->addClass('nav-link')
                        ->addParentClass('nav-item')
                );
            } elseif ($childRoutes->isNotEmpty()) {
                // Build dropdown submenu
                $submenuItems = '';
                foreach ($childRoutes as $child) {
                    $label = self::formatLabel($child);
                    $isActive = ($child === $currentRoute) ? ' active' : '';
                    $submenuItems .= sprintf(
                        '<li><a class="dropdown-item%s" href="%s">%s</a></li>',
                        $isActive,
                        route($child),
                        $label
                    );
                }

                if (trim($submenuItems) !== '') {
                    // Check if current route matches one of the children
                    $isActive = $childRoutes->contains($currentRoute) ? ' active' : '';

                    $dropdownHtml = sprintf(
                        '<li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle%s" href="#" id="dropdown-%s" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            %s
        </a>
        <ul class="dropdown-menu" aria-labelledby="dropdown-%s">
            %s
        </ul>
    </li>',
                        $isActive,     // %1$s
                        $mainItem,             // %2$s
                        self::formatLabel($mainItem),    // %3$s
                        $mainItem,             // %4$s again for aria-labelledby
                        $submenuItems          // %5$s
                    );

                    $menu->add(Html::raw(trim($dropdownHtml)));
                }
            }
        }

        return $menu;
    }

    private static function formatLabel(string $routeName): string
    {
        return RouteLabelHelper::formatLabel($routeName);
    }

    /**
     * Check if a route has no required parameters and has a name.
     *
     * @param  \Illuminate\Routing\Route  $route  The route to check
     * @return bool True if the route has no required parameters and has a name
     */
    private static function hasNoRequiredParameters(\Illuminate\Routing\Route $route): bool
    {
        $params = $route->parameterNames();

        return empty($params) && ! empty($route->getName());
    }
}
