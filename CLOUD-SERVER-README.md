# Cloud Server — NookTheme Fork

Fork of NookTheme v1.4.0 (Pterodactyl Panel v1.15.1) with the following customizations:

1. **Branding**: All user-visible "Pterodactyl" text replaced with **Cloud Server**.
2. **Login Loading Overlay**: 2-second animated loading screen after successful login (and after 2FA checkpoint).
3. **Enhanced Dashboard**: User dashboard gets a hero greeting card with gradient; admin dashboard gets stat widgets.
4. **Admin Owner-Only Security**: Only user ID 1 (configurable via `CLOUD_SERVER_OWNER_ID`) can access the following menus. Other admins see a dedicated **violation page** instead of the menu:
   - Locations
   - Databases
   - Nodes
   - Mounts
   - Nests
   - Settings
   - User delete
   - Server delete
   - Per-server admin view (`/admin/servers/view/{id}`)
5. **Auto-Suspend Scanner**: Scheduled every 5 minutes. Suspends servers that:
   - Exceed their configured disk limit.
   - Show suspicious activity (sustained high CPU, memory overrun).
6. **One-Shot Installer**: `php artisan cloudserver:install` activates branding + middleware + schedule + caches in a single command.

---

## Installation

After extracting the zip into your panel directory (e.g. `/var/www/pterodactyl`), run:

```bash
cd /var/www/pterodactyl
php artisan cloudserver:install
```

This will:

- Set `APP_NAME=Cloud Server` in `.env`
- Append `CLOUD_SERVER_OWNER_ID=1`, `CLOUD_SERVER_AUTOSUSPEND_ENABLED=true`, and tuning thresholds to `.env`
- Clear and rebuild config/route/view caches
- Run database migrations
- Verify that the `admin.owner` middleware alias is registered
- Verify that the protected admin routes are wired with the middleware
- Verify that the `cloudserver:autosuspend` schedule is registered

**Important**: After install, restart your queue worker and reload php-fpm/nginx so the new env values take effect:

```bash
php artisan queue:restart
systemctl reload php8.2-fpm nginx
```

Make sure your system cron runs the Laravel scheduler every minute (required for the auto-suspend scanner to fire):

```cron
* * * * * cd /var/www/pterodactyl && php artisan schedule:run >> /dev/null 2>&1
```

---

## Configuration

All tuning is via `.env`:

| Env var | Default | Purpose |
|---|---|---|
| `CLOUD_SERVER_OWNER_ID` | `1` | User ID that owns the panel. Only this user can access protected menus. |
| `CLOUD_SERVER_AUTOSUSPEND_ENABLED` | `true` | Master switch for the auto-suspend scanner. |
| `CLOUD_SERVER_DISK_THRESHOLD_PCT` | `100` | Disk usage % of limit before suspend. |
| `CLOUD_SERVER_SUSPICIOUS_CPU_PCT` | `99` | Sustained CPU% that triggers suspension. |
| `CLOUD_SERVER_SUSPICIOUS_MEM_OVERHEAD_PCT` | `20` | % over memory limit before suspend. |

---

## Manual Operations

Run the auto-suspend scanner once (without waiting for the schedule):

```bash
php artisan cloudserver:autosuspend
```

Dry run (show what would happen without actually suspending):

```bash
php artisan cloudserver:autosuspend --dry-run
```

---

## File Map (key new/modified files)

| File | Purpose |
|---|---|
| `app/Http/Middleware/AdminOwnerOnly.php` | Owner-only gate. Returns the violation view for non-owner admins. |
| `app/Http/Kernel.php` | Registers the `admin.owner` middleware alias. |
| `routes/admin.php` | Wraps Locations/Databases/Nodes/Mounts/Nests/Settings groups + user/server delete + per-server view with `admin.owner`. |
| `resources/views/admin/violation.blade.php` | Violation page shown when non-owner admin hits a protected route. |
| `app/Services/Servers/AutoSuspendService.php` | Suspends a server + writes activity log. |
| `app/Console/Commands/CloudServer/AutoSuspendCommand.php` | `cloudserver:autosuspend` artisan command. |
| `app/Console/Commands/CloudServer/InstallCommand.php` | `cloudserver:install` one-shot installer. |
| `app/Console/Kernel.php` | Schedules `cloudserver:autosuspend` every 5 minutes. |
| `resources/scripts/components/auth/PostLoginLoadingOverlay.tsx` | 2-second post-login loading overlay. |
| `resources/scripts/components/auth/LoginContainer.tsx` | Wires overlay into the login submit handler. |
| `resources/scripts/components/auth/LoginCheckpointContainer.tsx` | Wires overlay into the 2FA checkpoint handler. |
| `resources/scripts/components/auth/LoginFormContainer.tsx` | Rebrands login card (Cloud Server logo text + copyright). |
| `resources/scripts/components/dashboard/DashboardContainer.tsx` | Adds hero greeting card + stat strip. |
| `resources/views/admin/index.blade.php` | Adds 4 stat widgets + hero card. |
| `resources/views/layouts/admin.blade.php` | `config('app.name', 'Cloud Server')`. |
| `resources/views/templates/wrapper.blade.php` | Page title `Cloud Server`. |
| `config/app.php` | `APP_NAME` default → `Cloud Server`. |
| `config/mail.php` | `MAIL_FROM_NAME` default → `Cloud Server Panel`. |
| `resources/lang/en/strings.php` | `copyright` string updated. |

---

## GitHub

Source repository: <https://github.com/marwan585/opik.git>

---

## Notes & Caveats

- **PHP namespaces** are still `Pterodactyl\` to preserve composer autoload compatibility. Renaming 2,500+ files of PHPDoc references is mechanical but adds no user-visible value and risks breaking `composer dump-autoload`. The user-visible rebranding is what matters.
- **Docker image references** (`ghcr.io/pterodactyl/*`, `quay.io/pterodactyl/*`) are kept intact — they point to real upstream image registries that the eggs depend on.
- **The auto-suspend scanner relies on Wings being reachable.** If Wings is down for a node, servers on that node are skipped (logged as "daemon unreachable").
