# Tech Stack

## Backend
- **PHP 8.2+** with **Laravel 11**
- **MySQL** (default DB: `pfe_project`, port 3306)
- No authentication — single-user admin tool

## Frontend
- **Blade** templating (server-rendered)
- **Bootstrap 5.3** (loaded via CDN in the layout, also installed via npm)
- **Bootstrap Icons 1.11** (CDN)
- **DataTables 1.13** (CDN, Bootstrap 5 theme)
- **Vite** as the asset bundler (via `laravel-vite-plugin`)
- Custom CSS in `public/css/app-theme.css`

## Key Libraries
| Package | Purpose |
|---|---|
| `maatwebsite/excel` ^3.1 | Excel import (student/teacher data) |
| `barryvdh/laravel-dompdf` ^3.1 | PDF export (planning, affectation) |
| `phpoffice/phpword` ^1.1 | Word export (PVs, planning snapshots) |
| `laravel/pint` | Code style (PSR-12) |
| `phpunit/phpunit` ^11 | Testing |

## Common Commands

```bash
# First-time setup
composer run setup

# Development (starts PHP server + Vite + queue + log watcher concurrently)
composer run dev

# Run tests
composer run test
# or directly
php artisan test

# Build frontend assets
npm run build

# Database migrations
php artisan migrate

# Clear config cache
php artisan config:clear
```

## Environment
- Copy `.env.exemple` to `.env` and set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `APP_KEY` must be generated via `php artisan key:generate`
- CSRF validation is disabled for `import/*` routes (see `bootstrap/app.php`)
- Queue connection defaults to `database`; run `php artisan queue:listen` for async jobs
