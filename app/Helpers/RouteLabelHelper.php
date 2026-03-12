<?php

namespace App\Helpers;

class RouteLabelHelper
{
    // Map of route names to custom labels
    private static array $customLabels = [
        // Add your custom mappings here
        'prs' => 'PRs',
        'company-owner.index' => 'My Companies',
        'company-owner.edit' => 'Edit Company',
        'employment' => 'Employment',
        'employment.edit' => 'Edit Employment',
    ];

    public static function formatLabel(string $routeName): string
    {
        // Check if we have a custom label for this route
        if (isset(self::$customLabels[$routeName])) {
            return self::$customLabels[$routeName];
        }

        // If there's no dash, just format the whole route name
        if (!str_contains($routeName, '-')) {
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
