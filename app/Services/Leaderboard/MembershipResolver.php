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
     * @var array<string, list<array{org_id: int, from: CarbonInterface|null, to: CarbonInterface|null}>>
     */
    private readonly array $byLogin;

    /**
     * @param  array<string, list<array{org_id: int, from: CarbonInterface|null, to: CarbonInterface|null}>>  $byLogin
     */
    public function __construct(array $byLogin)
    {
        // Order each login's memberships so resolve()'s first covering match is
        // the intended one when ranges overlap: most recent start first (an open
        // `from` is the least specific, so it sorts last), tie-broken by the most
        // recent end (an open `to` is the most current). This makes a dated range
        // win over an open-ended profile suggestion without inspecting `source`.
        foreach ($byLogin as $login => $memberships) {
            usort($memberships, static function (array $a, array $b): int {
                $aFrom = $a['from']?->getTimestamp() ?? PHP_INT_MIN;
                $bFrom = $b['from']?->getTimestamp() ?? PHP_INT_MIN;
                if ($aFrom !== $bFrom) {
                    return $bFrom <=> $aFrom;
                }

                $aTo = $a['to']?->getTimestamp() ?? PHP_INT_MAX;
                $bTo = $b['to']?->getTimestamp() ?? PHP_INT_MAX;

                return $bTo <=> $aTo;
            });

            $byLogin[$login] = $memberships;
        }

        $this->byLogin = $byLogin;
    }

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
