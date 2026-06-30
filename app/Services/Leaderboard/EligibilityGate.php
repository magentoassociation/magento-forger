<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ScoredEvent;
use App\Models\RoleEligibility;

/**
 * Gates maintainer events by role: only people with maintainer rights —
 * the `maintainer` team plus the `community-council` committee (who hold the
 * same rights) — earn maintainer points. Contributor events are not gated.
 * If that combined roster is empty (not synced yet), gating is disabled and
 * everyone counts, so the system works before the first sync.
 *
 * Note: the public maintainer board is a separate concern (it lists only the
 * `maintainer` roster); this gate only governs who *earns* maintainer points.
 */
class EligibilityGate
{
    /**
     * @param  array<string, bool>  $maintainers  login => true (maintainer rights holders)
     */
    public function __construct(private readonly array $maintainers) {}

    public static function fromDatabase(): self
    {
        return new self(
            RoleEligibility::query()
                ->whereIn('role', ['maintainer', 'community-council'])
                ->pluck('login')
                ->flip()
                ->all(),
        );
    }

    public function allows(ScoredEvent $event): bool
    {
        if ($event->board !== Board::MAINTAINER) {
            return true;
        }

        return $this->maintainers === [] || isset($this->maintainers[$event->login]);
    }
}
