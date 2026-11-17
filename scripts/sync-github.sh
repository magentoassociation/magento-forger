#!/usr/bin/env bash
#
# @copyright Copyright (c) 2026 The Magento Association
# @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
#

# Sync GitHub data into OpenSearch, then recompute leaderboard scores. Use it for
# the first production load (full history) and for manual catch-ups locally or on
# a server. Routine production freshness still comes from the scheduler
# (routes/console.php).
#
# Usage: sync-github.sh                          full history (default)
#        sync-github.sh --since "1 week ago"     only items updated in the window
#        sync-github.sh --since="3 months"
#        Units: second, minute, hour, day, week, month, year (plural optional,
#        trailing "ago" optional).
#
# Failure safety. The data syncs only upsert documents, so a failed or
# interrupted sync never removes data; re-running fills in whatever it missed.
# leaderboard:compute is the one destructive step: it deletes and rewrites the
# board tables. It is skipped when any data sync failed, so a good board is never
# replaced by one computed from incomplete data. The syncs are otherwise
# independent: a failing one does not abort the rest, every failure is reported
# at the end, and the script exits non-zero.
set -euo pipefail

# run_step/run_profiles capture a step's exit status into a variable rather than
# letting it propagate, so `set -e` never sees a SIGINT'd step (exit 130) and the
# script moves on to the next stage. Trap INT explicitly so Ctrl-C kills the
# whole run, not just the step in flight.
trap 'echo "!! Interrupted" >&2; exit 130' INT

cd "$(dirname "$0")/.."

PERIOD=""
while [ $# -gt 0 ]; do
    case "$1" in
        --since=*) PERIOD="${1#*=}"; shift ;;
        --since)   PERIOD="${2:?--since needs a value, e.g. --since \"1 week ago\"}"; shift 2 ;;
        *) echo "Unknown argument: $1 (usage: sync-github.sh [--since \"<n> <unit> ago\"])" >&2; exit 2 ;;
    esac
done

since_args=()
WINDOW="full history"

if [ -n "$PERIOD" ]; then
    # Validate once, for both date implementations. GNU date would happily accept
    # expressions BSD date cannot express, and reads a bare "1 week" as a *future*
    # offset — so pin the input to the documented form and normalise the trailing
    # "ago" in, keeping the cutoff in the past on either host.
    if [[ ! "$PERIOD" =~ ^([0-9]+)[[:space:]]+(second|minute|hour|day|week|month|year)s?([[:space:]]+ago)?$ ]]; then
        echo "Error: could not parse period '$PERIOD' (expected e.g. \"1 week ago\")" >&2
        exit 1
    fi
    PERIOD_COUNT="${BASH_REMATCH[1]}"
    PERIOD_UNIT="${BASH_REMATCH[2]}"
    PERIOD="$PERIOD_COUNT $PERIOD_UNIT ago"

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
        case "$PERIOD_UNIT" in
            second) UNIT=S ;;
            minute) UNIT=M ;;
            hour)   UNIT=H ;;
            day)    UNIT=d ;;
            week)   UNIT=w ;;
            month)  UNIT=m ;;
            year)   UNIT=y ;;
        esac
        SINCE="$(date -u -v-"${PERIOD_COUNT}${UNIT}" '+%Y-%m-%d %H:%M:%S')"
    fi

    since_args=(--since="$SINCE")
    WINDOW="since: $SINCE"
fi

# Pick how to reach artisan:
#   - inside the ddev web container (`ddev sync-github`)    → php artisan
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

run_step "Syncing GitHub issues ($WINDOW)" \
    "${ARTISAN[@]}" sync:github:issues ${since_args[@]+"${since_args[@]}"} || DATA_SYNC_FAILED=1

run_step "Syncing GitHub PRs ($WINDOW)" \
    "${ARTISAN[@]}" sync:github:prs ${since_args[@]+"${since_args[@]}"} || DATA_SYNC_FAILED=1

run_step "Syncing GitHub interactions ($WINDOW)" \
    "${ARTISAN[@]}" sync:github:interactions ${since_args[@]+"${since_args[@]}"} || DATA_SYNC_FAILED=1

run_step "Syncing GitHub events ($WINDOW)" \
    "${ARTISAN[@]}" sync:github:events ${since_args[@]+"${since_args[@]}"} || DATA_SYNC_FAILED=1

# Teams and profiles do not gate the compute: a failed roster sync leaves the
# previous rosters in place (the command says so), and profiles only supply
# display names and avatars. Both still count toward the exit status.
run_step "Syncing GitHub teams" "${ARTISAN[@]}" sync:github:teams || true

run_profiles

if [ "$DATA_SYNC_FAILED" -eq 0 ]; then
    run_step "Computing leaderboard scores" "${ARTISAN[@]}" leaderboard:compute || true
else
    echo "!! Skipping leaderboard:compute — a data sync failed, so the existing board is left untouched" >&2
fi

if [ "${#FAILED[@]}" -gt 0 ]; then
    echo "==> Finished with failures:" >&2
    printf '  - %s\n' "${FAILED[@]}" >&2
    exit 1
fi

echo "==> Done"
