<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Helpers;

class RouteLabelHelper
{
    private static array $customLabels = [
        'prs' => 'PRs',
        'leaderboard.index' => 'Leaderboard',
    ];

    public static function formatLabel(string $routeName): string
    {
        // Check if we have a custom label for this route
        if (isset(self::$customLabels[$routeName])) {
            return self::$customLabels[$routeName];
        }

        $segments = explode('.', $routeName);
        $labelPart = end($segments);

        return ucwords(preg_replace('/([a-z])([A-Z])/', '$1 $2', $labelPart));
    }

    // Method to add or update a custom label
    public static function setCustomLabel(string $routeName, string $label): void
    {
        self::$customLabels[$routeName] = $label;
    }
}
