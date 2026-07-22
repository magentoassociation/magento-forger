#!/usr/bin/env bash
#
# @copyright Copyright (c) 2026 The Magento Association
# @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
#

# One-time production bootstrap. Populates OpenSearch with the full GitHub
# history and computes the initial leaderboard scores. Run once after the first
# deploy of the leaderboard feature; thereafter the scheduler keeps data fresh
# (see routes/console.php and scripts/sync-week.sh).
#
# Unlike sync-week.sh this omits --since, so every sync pulls the full history.
# That is heavy on the GitHub API. To scope it, set SINCE (e.g. SINCE="1 year")
# and it is passed to the data syncs that accept it.
set -euo pipefail

cd "$(dirname "$0")/.."

# Optional history window. Empty (default) = full history.
#   SINCE="1 year" ./scripts/bootstrap-prod.sh
SINCE="${SINCE:-}"
since_args=()
if [ -n "$SINCE" ]; then
    since_args=(--since="$SINCE")
fi

# Pick how to reach artisan:
#   - inside the ddev web container (`ddev bootstrap-prod`) → php artisan
#   - on a dev host with ddev installed                     → ddev artisan
#   - anywhere else (plain server, CI, prod)                → php artisan
if [ "${IS_DDEV_PROJECT:-}" = "true" ]; then
    ARTISAN=(php artisan)
elif command -v ddev >/dev/null 2>&1; then
    ARTISAN=(ddev artisan)
else
    ARTISAN=(php artisan)
fi

# 1. Maintainer/council roster (source of truth = GitHub teams). Supplement with
#    leaderboard:import-eligibility if you have roster rows not in GitHub teams.
echo "==> Syncing GitHub teams (maintainer/council roster)"
"${ARTISAN[@]}" sync:github:teams

# 2. Full historical data into OpenSearch.
echo "==> Syncing GitHub issues${SINCE:+ (since: $SINCE)}"
"${ARTISAN[@]}" sync:github:issues ${since_args[@]+"${since_args[@]}"}

echo "==> Syncing GitHub PRs${SINCE:+ (since: $SINCE)}"
"${ARTISAN[@]}" sync:github:prs ${since_args[@]+"${since_args[@]}"}

echo "==> Syncing GitHub interactions${SINCE:+ (since: $SINCE)}"
"${ARTISAN[@]}" sync:github:interactions ${since_args[@]+"${since_args[@]}"}

echo "==> Syncing GitHub events${SINCE:+ (since: $SINCE)}"
"${ARTISAN[@]}" sync:github:events ${since_args[@]+"${since_args[@]}"}

# 3. Display names + avatars for everyone who appeared above.
echo "==> Syncing GitHub profiles"
"${ARTISAN[@]}" sync:github:profiles

# 4. Compute leaderboard scores from the synced data.
echo "==> Computing leaderboard scores"
"${ARTISAN[@]}" leaderboard:compute

echo "==> Done"
