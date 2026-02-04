<?php
namespace App\Menus;

use App\Helpers\RouteLabelHelper;
use Illuminate\Support\Facades\Route;
use Spatie\Menu\Laravel\Menu;
use Spatie\Menu\Laravel\Html;
use Spatie\Menu\Laravel\Link;

class MainMenu
{
    private const MENU_ROUTE_PATTERN = '/^(home|issues|prs|labels)(-[\w]+)?$/';

    public static function build(): Menu
    {
        $currentRoute = Route::currentRouteName();

        $routes = collect(Route::getRoutes())
            ->filter(fn($route) => self::hasNoRequiredParameters($route))
            ->filter(fn($route) => in_array('GET', $route->methods()))
            // TODO: Add a new filter to restrict these routes to admin access only
            ->map(fn($route) => $route->getName())
            ->filter(fn($name) => preg_match(self::MENU_ROUTE_PATTERN, $name));

        $menu = Menu::new()
            ->addClass('navbar-nav me-auto mb-2 mb-lg-0')
            ->setActiveClassOnLink()
            ->setActiveFromRequest();

        $grouped = $routes->groupBy(fn($name) => explode('-', $name)[0]);

        foreach ($grouped as $mainItem => $subRoutes) {
            $mainRouteExists = $subRoutes->contains($mainItem);
            $childRoutes = $subRoutes->filter(fn($name) => $name !== $mainItem);

            if ($mainRouteExists && $childRoutes->isEmpty()) {
                // Single item, no submenu
                $menu->add(
                    Link::toRoute($mainItem, self::formatLabel($mainItem))
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
                        $isActive,             // %1$s
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
     * @param \Illuminate\Routing\Route $route The route to check
     * @return bool True if the route has no required parameters and has a name
     */
    private static function hasNoRequiredParameters($route): bool
    {
        $params = $route->parameterNames();
        return empty($params) && !empty($route->getName());
    }
}
