#!/usr/bin/env bash
#
# @copyright Copyright (c) 2026 The Magento Association
# @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
#

# Sync a window of GitHub data, then recompute leaderboard scores.
# Runs every sync:github:* command that accepts a --since date filter.
#
# The syncs are independent, so a failing one does not abort the rest: each
# status is captured and reported at the end, and the script exits non-zero if
# anything failed. leaderboard:compute is the exception — it reads what the data
# syncs wrote, so it is skipped when one of them failed rather than publishing a
# board computed from an incomplete window.
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

FAILED=()
DATA_SYNC_FAILED=0

# Run one stage, record its failure instead of aborting the run, and pass the
# status back so the caller can decide whether it also gates later stages.
run_step() {
    local label="$1"
    shift
    local status=0

    echo "==> $label"
    "$@" || status=$?

    if [ "$status" -ne 0 ]; then
        echo "!! $label failed (exit $status)" >&2
        FAILED+=("$label")
    fi

    return "$status"
}

# sync:github:profiles exits 0 even when individual fetches error out: it logs
# each one and reports the tally as "failed: N". Read that count back so API
# failures still register, while keeping the command's output streaming.
run_profiles() {
    local status=0 log
    log="$(mktemp)"

    echo "==> Syncing GitHub profiles"
    "${ARTISAN[@]}" sync:github:profiles 2>&1 | tee "$log" || status=$?

    if [ "$status" -ne 0 ]; then
        echo "!! Syncing GitHub profiles failed (exit $status)" >&2
        FAILED+=("Syncing GitHub profiles")
    elif [[ "$(cat "$log")" =~ failed:\ ([0-9]+) ]] && [ "${BASH_REMATCH[1]}" -gt 0 ]; then
        echo "!! ${BASH_REMATCH[1]} GitHub profile fetch(es) failed" >&2
        FAILED+=("GitHub profile fetches (${BASH_REMATCH[1]} failed)")
    fi

    rm -f "$log"
}

run_step "Syncing GitHub issues (since: $SINCE)" \
    "${ARTISAN[@]}" sync:github:issues --since="$SINCE" || DATA_SYNC_FAILED=1

run_step "Syncing GitHub PRs (since: $SINCE)" \
    "${ARTISAN[@]}" sync:github:prs --since="$SINCE" || DATA_SYNC_FAILED=1

run_step "Syncing GitHub interactions (since: $SINCE)" \
    "${ARTISAN[@]}" sync:github:interactions --since="$SINCE" || DATA_SYNC_FAILED=1

run_step "Syncing GitHub events (since: $SINCE)" \
    "${ARTISAN[@]}" sync:github:events --since="$SINCE" || DATA_SYNC_FAILED=1

# Teams and profiles do not gate the compute: a failed roster sync leaves the
# previous rosters in place (the command says so), and profiles only supply
# display names and avatars. Both still count toward the exit status.
run_step "Syncing GitHub teams" "${ARTISAN[@]}" sync:github:teams || true

run_profiles

if [ "$DATA_SYNC_FAILED" -eq 0 ]; then
    run_step "Computing leaderboard scores" "${ARTISAN[@]}" leaderboard:compute || true
else
    echo "!! Skipping leaderboard:compute — a data sync failed, so the window is incomplete" >&2
fi

if [ "${#FAILED[@]}" -gt 0 ]; then
    echo "==> Finished with failures:" >&2
    printf '  - %s\n' "${FAILED[@]}" >&2
    exit 1
fi

echo "==> Done"
