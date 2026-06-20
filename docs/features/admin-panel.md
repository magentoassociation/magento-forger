# Admin Panel

## Purpose

Provide admins with a UI to view and delete registered Users. Accessible at `/admin` (Filament panel).

## How it works

Filament v3 panel configured via `AdminPanelProvider`. Single resource: `UserResource`.

### UserResource

- **List**: searchable/sortable table of `name`, `github_username`, `email`.
- **Edit**: read-only form (name, email, github_username, github_id are disabled — data syncs from GitHub on login and cannot be manually edited).
- **Delete**: available per-row and as a bulk action.
- **Create**: disabled. All users are created via GitHub OAuth only.

### GitHubStats widget

`app/Filament/Widgets/GitHubStats.php` — displays GitHub statistics on the Filament dashboard. Queries OpenSearch.

### Access control

Only users with `is_admin = true` can access the panel. Enforced by Filament's `FilamentUser` contract on the `User` model — or via `AdminPanelProvider` gate configuration. Promote users with:

```bash
php artisan make:user:admin {email}
```

## Key files

- `app/Filament/Resources/UserResource.php` — CRUD resource
- `app/Filament/Resources/UserResource/Pages/ListUsers.php`
- `app/Filament/Resources/UserResource/Pages/EditUser.php`
- `app/Filament/Widgets/GitHubStats.php`
- `app/Providers/Filament/AdminPanelProvider.php` — Panel registration, path, auth

## Configuration

Panel path: `/admin` (default Filament). Change in `AdminPanelProvider::panel()`.

## Gotchas / constraints

- User form fields are `disabled()` + `dehydrated(false)` — they display current values but submit nothing. This prevents accidental overwrites of GitHub-sourced data.
- The `CreateUser` page class exists in the filesystem but is not registered in `getPages()`. Do not register it — user creation goes through OAuth.
- Filament requires `php artisan filament:optimize` before production deploys for asset compilation.
