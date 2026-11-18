# Deployment

## Purpose

Ship releases to production, and push changed secrets (passwords, keys, tokens) without a release.

## How it works

Deployer (`deploy.yaml`, hosts in `deployer-hosts.php`) is driven by `deploy.sh`, locally or from the manually dispatched **Build and Deploy** workflow (`.github/workflows/deploy.yml`). Two tasks:

| Task | What it does |
|---|---|
| `forger:deploy` (default) | Builds assets, uploads a new release, migrates, switches `current`, reloads php-fpm, purges Varnish. |
| `forger:env` | Pushes a rebuilt `.env` to `shared/.env`, re-caches config, restarts queue workers, reloads php-fpm. No new release. |

Both build `.env` the same way (`env:create`). In Actions it is rendered from `.env.template` with the repository secrets. Locally, `.env.deploy` is copied as is. Both finish with `app:check-connections`, which fails the run (red ✗ per service) if the live `.env` cannot reach the database, the Redis connections the cache and session stores use, or OpenSearch.

Run locally with `./deploy.sh [forger:deploy|forger:env]`, or pick the task in the workflow's **task** input.

**`.env` on the server is owned by the deploy.** Every `forger:deploy` and `forger:env` overwrites `shared/.env`, so a value edited by hand on the server is lost on the next run.

**There are two sources, and the next run wins.** A run from Actions takes the GitHub secrets. A run from a machine takes that machine's `.env.deploy`. A secret changed in only one of them is reverted by the next run from the other, and if it's a password the site then can't connect. `app:check-connections` fails that run, but only after the stale `.env` is already live. Change a value in **both** places, or deploy from one route only.

## Rotating a secret

`forger:env` only changes what the app is told. It does not change any password at the service itself.

**Every rotation goes in both sources: the GitHub secret and `.env.deploy` on every machine that deploys.** Otherwise the next run from the stale source puts the old value back (see above).

### Database, Redis or OpenSearch password

1. Change the password at the service (MariaDB, Redis or OpenSearch, via JetRails).
2. Immediately update the GitHub secret (`DB_PASSWORD`, `REDIS_PASSWORD`, `REDIS_SESSION_PASSWORD`, `REDIS_FPC_PASSWORD`, `OPENSEARCH_PASSWORD`) **and** `.env.deploy` on every machine that deploys.
3. Run `forger:env`.

Between steps 1 and 3 the site cannot connect to that service, so keep the gap short. If `app:check-connections` fails, the secret doesn't match what the service now expects. Fix the secret and run `forger:env` again.

### GitHub token or OAuth secret

Create the new token or secret on GitHub, update `GH_SYNC_PAT_CLASSIC` / `OAUTH_CLIENT_SECRET` and `GITHUB_TOKEN` / `GITHUB_CLIENT_SECRET` in `.env.deploy`, run `forger:env`, then revoke the old one. There's no gap: both stay valid until you revoke.

### APP_KEY

`APP_KEY` encrypts sessions and cookies. Nothing else in the app is encrypted with it at rest. Replacing it outright logs everyone out. To rotate without that:

1. `php artisan key:generate --show` for a new key.
2. Set `APP_PREVIOUS_KEYS` to the current key (comma-separate if there are several), and `APP_KEY` to the new one, in the GitHub secrets and `.env.deploy`.
3. Run `forger:env`. Laravel encrypts with the new key and still decrypts with the old ones.
4. Once sessions encrypted with the old key have expired (`SESSION_LIFETIME`), clear `APP_PREVIOUS_KEYS` and run `forger:env` again.

## Key files

- `deploy.yaml` — tasks, including `forger:env` and `artisan:check-connections`
- `deploy.sh` — entry point; picks the jump host in Actions
- `.github/workflows/deploy.yml` — manual workflow; secrets → env
- `.env.template` — rendered with the secrets in Actions
- `app/Console/Commands/CheckConnections.php` — post-deploy connection check
