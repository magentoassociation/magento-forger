#!/usr/bin/env bash
#
# @copyright Copyright (c) 2026 The Magento Association
# @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
#

# Sync the last week of GitHub data, then recompute leaderboard scores.
# Runs every sync:github:* command that accepts a --since date filter.
set -euo pipefail

cd "$(dirname "$0")/.."

# Freeze a single absolute cutoff so every stage below filters from the same
# instant. Passing the relative "-1 week" to each command would re-evaluate it
# per stage, drifting the cutoff as the run progresses.
if date -u -d "-1 week" '+%Y-%m-%d %H:%M:%S' >/dev/null 2>&1; then
    SINCE="$(date -u -d '-1 week' '+%Y-%m-%d %H:%M:%S')"  # GNU date (Linux/CI/prod)
else
    SINCE="$(date -u -v-1w '+%Y-%m-%d %H:%M:%S')"         # BSD date (macOS dev host)
fi

# Pick how to reach artisan:
#   - inside the ddev web container (`ddev sync-week`) → php artisan
#   - on a dev host with ddev installed              → ddev artisan
#   - anywhere else (plain server, CI, prod)         → php artisan
if [ "${IS_DDEV_PROJECT:-}" = "true" ]; then
    ARTISAN=(php artisan)
elif command -v ddev >/dev/null 2>&1; then
    ARTISAN=(ddev artisan)
else
    ARTISAN=(php artisan)
fi

echo "==> Syncing GitHub issues (since: $SINCE)"
"${ARTISAN[@]}" sync:github:issues --since="$SINCE"

echo "==> Syncing GitHub PRs (since: $SINCE)"
"${ARTISAN[@]}" sync:github:prs --since="$SINCE"

echo "==> Syncing GitHub interactions (since: $SINCE)"
"${ARTISAN[@]}" sync:github:interactions --since="$SINCE"

echo "==> Syncing GitHub events (since: $SINCE)"
"${ARTISAN[@]}" sync:github:events --since="$SINCE"

echo "==> Syncing GitHub teams"
"${ARTISAN[@]}" sync:github:teams

echo "==> Syncing GitHub profies"
"${ARTISAN[@]}" sync:github:profiles

echo "==> Computing leaderboard scores"
"${ARTISAN[@]}" leaderboard:compute

echo "==> Done"
