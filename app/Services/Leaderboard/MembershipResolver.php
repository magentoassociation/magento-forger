<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Leaderboard;

use App\Models\UserOrgMembership;
use Carbon\CarbonInterface;

/**
 * Point-in-time resolver: which organization a contributor belonged to on a
 * given date. A null `from`/`to` means open-ended. Returns null when no
 * membership covers the date (→ "Unknown").
 */
class MembershipResolver
{
    /**
     * @param  array<string, list<array{org_id: int, from: CarbonInterface|null, to: CarbonInterface|null}>>  $byLogin
     */
    public function __construct(private readonly array $byLogin) {}

    public static function fromDatabase(): self
    {
        $byLogin = [];

        foreach (UserOrgMembership::all() as $membership) {
            $byLogin[$membership->login][] = [
                'org_id' => $membership->organization_id,
                'from' => $membership->from_date,
                'to' => $membership->to_date,
            ];
        }

        return new self($byLogin);
    }

    public function resolve(string $login, CarbonInterface $date): ?int
    {
        foreach ($this->byLogin[$login] ?? [] as $membership) {
            $afterStart = $membership['from'] === null || $date->greaterThanOrEqualTo($membership['from']);
            $beforeEnd = $membership['to'] === null || $date->lessThanOrEqualTo($membership['to']);

            if ($afterStart && $beforeEnd) {
                return $membership['org_id'];
            }
        }

        return null;
    }
}
