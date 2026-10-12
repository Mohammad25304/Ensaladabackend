# Ensalada — Backend API

Laravel 13 API + Filament admin panel for the Ensalada restaurant website
(branches, categories, menu items with per-branch prices, site settings,
contact messages, FAQs and a rule-based chatbot).

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# edit .env: DB settings, CORS_ALLOWED_ORIGINS, ADMIN_EMAIL, ADMIN_PASSWORD
php artisan migrate
php artisan db:seed          # creates the first admin (prints a password if ADMIN_PASSWORD is empty)
php artisan storage:link     # needed for uploaded menu images
php artisan serve
```

Admin panel: `/admin` (admins only). API: `/api/...`

## Important `.env` values

| Variable | Purpose |
| --- | --- |
| `APP_DEBUG` | Must be `false` in production |
| `APP_URL` | Public URL of this API (used to build image URLs) |
| `CORS_ALLOWED_ORIGINS` | Comma-separated frontend origins, e.g. `https://your-site.vercel.app` |
| `SANCTUM_TOKEN_EXPIRATION` | Token lifetime in minutes (default 10080 = 7 days) |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | First admin account created by the seeder |

## Notes

- Everything under `/api/admin/*` requires a Sanctum token **and** `role = admin`.
- Uploaded images go to the `public` disk. On hosts with an ephemeral
  filesystem (e.g. Railway without a volume) they are lost on redeploy — attach
  a volume or switch `FILESYSTEM_DISK`/the upload disk to S3.

## Tests

```bash
php artisan test
```
