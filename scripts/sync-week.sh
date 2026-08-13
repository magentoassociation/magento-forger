#!/usr/bin/env bash
#
# @copyright Copyright (c) 2026 The Magento Association
# @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
#

# Sync a window of GitHub data, then recompute leaderboard scores.
# Runs every sync:github:* command that accepts a --since date filter.
#
# Usage: sync-week.sh ["<n> <unit> ago"]   e.g. "1 week ago" (default), "1 month ago"
#        Units: second, minute, hour, day, week, month, year (plural optional,
#        trailing "ago" optional).
set -euo pipefail

cd "$(dirname "$0")/.."

PERIOD="${1:-1 week ago}"

# Freeze a single absolute cutoff so every stage below filters from the same
# instant. Passing the relative period to each command would re-evaluate it
# per stage, drifting the cutoff as the run progresses.
if date -u -d '-1 week' '+%Y-%m-%d %H:%M:%S' >/dev/null 2>&1; then
    # GNU date (Linux/CI/prod) parses the period expression directly.
    if ! SINCE="$(date -u -d "$PERIOD" '+%Y-%m-%d %H:%M:%S' 2>/dev/null)"; then
        echo "Error: could not parse period '$PERIOD'" >&2
        exit 1
    fi
else
    # BSD date (macOS dev host) has no expression parser: translate the period
    # into a -v adjustment.
    if [[ ! "$PERIOD" =~ ^([0-9]+)[[:space:]]+(second|minute|hour|day|week|month|year)s?([[:space:]]+ago)?$ ]]; then
        echo "Error: could not parse period '$PERIOD' (expected e.g. \"1 week ago\")" >&2
        exit 1
    fi
    case "${BASH_REMATCH[2]}" in
        second) UNIT=S ;;
        minute) UNIT=M ;;
        hour)   UNIT=H ;;
        day)    UNIT=d ;;
        week)   UNIT=w ;;
        month)  UNIT=m ;;
        year)   UNIT=y ;;
    esac
    SINCE="$(date -u -v-"${BASH_REMATCH[1]}${UNIT}" '+%Y-%m-%d %H:%M:%S')"
fi

# Pick how to reach artisan:
#   - inside the ddev web container (`ddev sync-week`)      → php artisan
#   - on a dev host, in a ddev project, with ddev installed → ddev artisan
#   - anywhere else (plain server, CI, prod)                → php artisan
#
# The ddev binary alone is not enough: a checkout without .ddev/config.yaml has
# no project for `ddev artisan` to attach to.
if [ "${IS_DDEV_PROJECT:-}" = "true" ]; then
    ARTISAN=(php artisan)
elif [ -f .ddev/config.yaml ] && command -v ddev >/dev/null 2>&1; then
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

echo "==> Syncing GitHub profiles"
"${ARTISAN[@]}" sync:github:profiles

echo "==> Computing leaderboard scores"
"${ARTISAN[@]}" leaderboard:compute

echo "==> Done"
