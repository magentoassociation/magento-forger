<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for bot/automation accounts excluded from leaderboards.
 * Reads from config('leaderboard.bots'). Provides both OpenSearch `must_not`
 * clauses (for any keyword field) and an in-PHP membership check.
 */
class BotFilter
{
    /**
     * OpenSearch `must_not` clauses excluding bots on the given keyword field
     * (e.g. `author.keyword`, `actor.keyword`, `github_account_name.keyword`).
     *
     * @return list<array<string, mixed>>
     */
    public static function mustNot(string $field): array
    {
        $clauses = [];

        foreach (self::prefixes() as $prefix) {
            $clauses[] = ['wildcard' => [$field => $prefix.'*']];
        }

        foreach (self::exact() as $login) {
            $clauses[] = ['term' => [$field => $login]];
        }

        return $clauses;
    }

    public static function isBot(string $login): bool
    {
        foreach (self::prefixes() as $prefix) {
            if (str_starts_with($login, $prefix)) {
                return true;
            }
        }

        return in_array($login, self::exact(), true);
    }

    /**
     * @return list<string>
     */
    private static function exact(): array
    {
        return array_values((array) config('leaderboard.bots.exact', []));
    }

    /**
     * @return list<string>
     */
    private static function prefixes(): array
    {
        return array_values((array) config('leaderboard.bots.prefixes', []));
    }
}
