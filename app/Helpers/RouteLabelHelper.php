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
    ];

    public static function formatLabel(string $routeName): string
    {
        // Check if we have a custom label for this route
        if (isset(self::$customLabels[$routeName])) {
            return self::$customLabels[$routeName];
        }

        // If there's no dash, just format the whole route name
        if (! str_contains($routeName, '-')) {
            return ucwords(preg_replace('/([a-z])([A-Z])/', '$1 $2', $routeName));
        }

        // If there is a dash, split and use the second part
        [, $labelPart] = explode('-', $routeName, 2);

        return ucwords(preg_replace('/([a-z])([A-Z])/', '$1 $2', $labelPart));
    }

    // Method to add or update a custom label
    public static function setCustomLabel(string $routeName, string $label): void
    {
        self::$customLabels[$routeName] = $label;
    }
}
