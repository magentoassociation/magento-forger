#!/bin/bash
set -e
set -o pipefail

# forger:deploy (default) for a full release; forger:env to push only a rebuilt .env.
TASK="${1:-forger:deploy}"
if [[ "$TASK" != "forger:deploy" && "$TASK" != "forger:env" ]]; then
    echo "Usage: $0 [forger:deploy|forger:env]"
    exit 1
fi

DEPLOY_HOST="forger"
if [[ "${GITHUB_ACTIONS}" == "true" ]]; then
    echo "Running in GitHub Actions - using environment variables from secrets"
    DEPLOY_HOST="forger-jump"
else
    echo "Running locally - sourcing deployment configuration"
    if [[ ! -f .env.deploy ]]; then
        echo "Error: .env.deploy file not found"
        echo "Create .env.deploy with your production values when using local deployments"
        exit 1
    fi

    set -a
    source .env.deploy
    set +a
fi

echo "Running $TASK on production..."
sshpass -e dep "$TASK" "$DEPLOY_HOST"
echo "$TASK complete!"
