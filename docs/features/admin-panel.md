# Admin Panel

## Purpose

Provide admins with a UI to view and delete registered Users. Accessible at `/admin` (Filament panel).

## How it works

Filament v3 panel configured via `AdminPanelProvider`. Single resource: `UserResource`.

### UserResource

- **List**: searchable/sortable table of `name`, `github_username`, `github_id`, `email`.
- **Delete**: available per-row and as a bulk action.
- **Create**: disabled (`canCreate()` returns `false`). All users are created via GitHub OAuth only.
- **Edit**: *not implemented.* Only the list page is registered in `getPages()`; there is no edit page and `form()` returns an empty schema. **(Planned)** a read-only form showing GitHub-sourced fields.

### Access control

Only users with `is_admin = true` can access the panel. Enforced by Filament's `FilamentUser` contract on the `User` model (`User::canAccessPanel()` checks `is_admin`). Promote users with:

```bash
php artisan app:make-user-admin
```

The command takes no argument — it prompts for the user email interactively.

## Key files

- `app/Filament/Resources/UserResource.php` — list + delete resource (no create/edit)
- `app/Filament/Resources/UserResource/Pages/ListUsers.php`
- `app/Providers/Filament/AdminPanelProvider.php` — Panel registration, path, auth

## Configuration

Panel path: `/admin` (default Filament). Change in `AdminPanelProvider::panel()`.

## Gotchas / constraints

- User creation goes through OAuth only; `canCreate()` returns `false` and no create/edit page is registered.
- Filament requires `php artisan filament:optimize` before production deploys for asset compilation.
