# Authentication

## Purpose

Allow Magento Association members to log in via GitHub OAuth. Grants access to the application and links the registered User to a GitHub login for contributor attribution.

## How it works

GitHub OAuth via Laravel Socialite v5. No email/password registration — all accounts are created through the OAuth callback.

### Login flow

1. User visits `/auth/github` → redirected to GitHub for OAuth consent.
2. GitHub redirects to `/auth/github/callback` with an auth code.
3. `LoginController::handleGitHubCallback()` exchanges the code for a user via Socialite.
4. `User::updateOrCreate(['github_id' => ...], [...])` — finds or creates the User record.
5. `Auth::login($user)` → redirect to home.

Email fallback: if GitHub returns no verified email (rare), the user record is stored with `{github_id}@github.noreply.local` as the email.

### Admin role

Users are promoted to admin manually via the `make:user:admin` Artisan command:

```bash
php artisan make:user:admin {email}
```

Admin users gain access to:
- Label processing upload form (`/labels/process-labels`)
- Filament admin panel (`/admin`)

The `AdminMiddleware` checks `Auth::user()->is_admin`.

### Rate limiting

The OAuth callback route is rate-limited to **10 requests per minute** per IP.

## Key files

- `app/Http/Controllers/Auth/LoginController.php` — OAuth redirect + callback
- `app/Models/User.php` — `github_id`, `github_username`, `is_admin` fields
- `app/Http/Middleware/AdminMiddleware.php` — Admin gate
- `app/Console/Commands/MakeUserAdmin.php` — Admin promotion command
- `config/services.php` — GitHub OAuth client ID + secret
- `routes/web.php` — `/auth/github`, `/auth/github/callback`, `/login`, `/logout`

## Configuration

| Key | Description |
|-----|-------------|
| `GITHUB_CLIENT_ID` | OAuth app client ID |
| `GITHUB_CLIENT_SECRET` | OAuth app client secret |
| `GITHUB_REDIRECT_URI` | Callback URL registered in the GitHub OAuth app |

## Gotchas / constraints

- `UpdateOrCreate` matches on `github_id`, not email. If a user changes their GitHub email, their Forger email updates on next login.
- `InvalidStateException` (CSRF mismatch / expired session) redirects home with an error rather than crashing.
- Users cannot be created through the Filament admin panel — `UserResource::canCreate()` returns `false`. All accounts come from OAuth.
- Logout is a `POST` (CSRF-protected), not a `GET`.
