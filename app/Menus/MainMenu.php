<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Menus;

use App\Helpers\RouteLabelHelper;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\Menu\Laravel\Html;
use Spatie\Menu\Laravel\Link;
use Spatie\Menu\Laravel\Menu;

class MainMenu
{
    // All nav links temporarily hidden — only the logo shows in the navbar.
    // Restore the pattern below to bring the menu back.
    private const MENU_ROUTE_PATTERN = '/^(home|leaderboard\.index|issues|prs|employment)(\.[\w]+)?$/';

    /**
     * Submenu links for routes that need parameters, which the route scan skips.
     * `active` lists the route patterns the item is current on; `board` narrows it.
     *
     * @var array<string, list<array{label: string, route: string, params: array<string, string>, active: list<string>, board?: string}>>
     */
    private const PARAMETERIZED_CHILDREN = [
        'leaderboard' => [
            ['label' => 'Contributor', 'route' => 'leaderboard.show', 'params' => ['board' => 'contributor'], 'active' => ['leaderboard.show', 'leaderboard.detail'], 'board' => 'contributor'],
            ['label' => 'Maintainer', 'route' => 'leaderboard.show', 'params' => ['board' => 'maintainer'], 'active' => ['leaderboard.show', 'leaderboard.detail'], 'board' => 'maintainer'],
            ['label' => 'Monthly', 'route' => 'leaderboard.monthly.index', 'params' => ['board' => 'contributor'], 'active' => ['leaderboard.monthly*']],
            ['label' => 'Highlights', 'route' => 'leaderboard.highlights', 'params' => [], 'active' => ['leaderboard.highlights']],
        ],
    ];

    public static function build(): Menu
    {
        $currentRoute = Route::currentRouteName();

        $routes = collect(Route::getRoutes())
            ->filter(fn (\Illuminate\Routing\Route $route): bool => self::hasNoRequiredParameters($route))
            ->filter(fn (\Illuminate\Routing\Route $route): bool => in_array('GET', $route->methods(), true))
            ->map(fn ($route) => $route->getName())
            ->filter(fn ($name) => preg_match(self::MENU_ROUTE_PATTERN, $name))
            ->reject(fn (string $name): bool => $name === 'prs.communityPicked' && Gate::denies('view-community-picked'));

        $menu = Menu::new()
            ->addClass('navbar-nav me-auto mb-2 mb-lg-0')
            ->setActiveClassOnLink()
            ->setActiveFromRequest();

        $grouped = $routes->groupBy(fn (string $name): string => explode('.', $name)[0]);

        foreach ($grouped as $mainItem => $subRoutes) {
            $landingRoute = $subRoutes->first(fn (string $name): bool => $name === $mainItem || $name === "{$mainItem}.index");
            $children = $subRoutes
                ->filter(fn (string $name): bool => $name !== $landingRoute)
                ->map(fn (string $name): array => [
                    'label' => self::formatLabel($name),
                    'url' => route($name),
                    'active' => $name === $currentRoute,
                ])
                ->concat(array_map(self::parameterizedChild(...), self::PARAMETERIZED_CHILDREN[$mainItem] ?? []))
                ->values();

            if ($landingRoute && $children->isEmpty()) {
                $menu->add(
                    Link::toRoute($landingRoute, self::formatLabel($landingRoute))
                        ->addClass('nav-link')
                        ->addParentClass('nav-item')
                );
            } elseif ($children->isNotEmpty()) {
                $submenuItems = $children->map(fn (array $child): string => sprintf(
                    '<li><a class="dropdown-item%s"%s href="%s">%s</a></li>',
                    $child['active'] ? ' active' : '',
                    $child['active'] ? ' aria-current="page"' : '',
                    $child['url'],
                    $child['label']
                ))->implode('');

                // A landing route makes the parent a real link: on hover devices a click
                // follows it (the hover already opened the menu); elsewhere it toggles.
                $dropdownHtml = sprintf(
                    '<li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle%s" href="%s" id="dropdown-%s" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            %s%s
        </a>
        <ul class="dropdown-menu" aria-labelledby="dropdown-%s">
            %s
        </ul>
    </li>',
                    $children->contains('active', true) || request()->routeIs("{$mainItem}.*") ? ' active' : '',
                    $landingRoute ? route($landingRoute) : '#',
                    $mainItem,
                    self::formatLabel($landingRoute ?? $mainItem),
                    trim(view('components._caret')->render()),
                    $mainItem,
                    $submenuItems
                );

                $menu->add(Html::raw(trim($dropdownHtml)));
            }
        }

        // Items are activated by setActiveFromRequest's filter as they're added.
        return $menu->each(function (Link $link): void {
            if ($link->isActive()) {
                $link->setAttribute('aria-current', 'page');
            }
        });
    }

    /**
     * @param  array{label: string, route: string, params: array<string, string>, active: list<string>, board?: string}  $child
     * @return array{label: string, url: string, active: bool}
     */
    private static function parameterizedChild(array $child): array
    {
        $active = request()->routeIs(...$child['active'])
            && (! isset($child['board']) || request()->route('board') === $child['board']);

        return ['label' => $child['label'], 'url' => route($child['route'], $child['params']), 'active' => $active];
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
