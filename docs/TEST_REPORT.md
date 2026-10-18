# Test report

Date: 16 October 2026. Machine: Windows, PHPUnit via `php artisan test`, Vitest 4.1.11, Vite 8.3.4. PostgreSQL 18.2 on `127.0.0.1:5433` (Laragon). No production database was migrated or dumped.

## Commands and results

| Command | Working directory | Result |
| --- | --- | --- |
| `php artisan test` | `api` | **Failed first.** PostgreSQL was not listening. 16 tests, 3 passed, 13 errors: `connection refused` on port 5433, then `the database system is starting up`. |
| Laragon `pg_ctl` start on port 5433 | data directory `C:\laragon\data\postgresql` | Server started. `pg_isready` reported accepting connections. |
| `php artisan test` | `api` | **Passed.** 16 tests, 97 assertions, 4131 ms. |
| `composer audit --no-interaction --locked` | `api` | First attempt timed out contacting packagist. Retry: `No security vulnerability advisories found.` |
| `npm test` | `web` | **Passed.** 2 tests, 2 files. |
| `npm run build` | `web` | **Passed.** `tsc -b` and Vite build. Output `dist/assets/index-9TjONEHA.js` (322.50 kB, gzip 95.13 kB) and CSS 5.23 kB. |
| `npm audit --json` | `web` | 0 info, low, moderate, high, and critical advisories. 165 dependencies counted. |
| `GET http://127.0.0.1:8000/api/v1/health` | local API | `{"data":{"status":"ok","database":"ok"}}` with `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`. |

The first PHPUnit failure was the database being down, not an assertion failure. The second run is the result that counts.

## Browser

Local servers: `php artisan serve` on port 8000 and `npm run dev` on port 5173.

1. Opened `/staff/login`.
2. Signed in as `ada.admin@aurora.test` with the local demo password.
3. Landed on `/staff` as hotel administrator for Aurora Suites Lagos.
4. Opened `/admin/rooms/1/print`.
5. The card showed the hotel name and a QR image, not injected SVG markup.

Guest ordering was not clicked through again in this pass. `HotelOperationsTest` covers guest validation, server-side totals, privacy, duplicates, feedback, and status rules.

## Production boot drill

Date: 17 October 2026. PostgreSQL 18.2 on `127.0.0.1:5433`. The application key already stored in `api/.env` was used and was not printed. `APP_ENV=production` and `APP_DEBUG=false` were set only for this process.

| Step | Result |
| --- | --- |
| Create empty database `git_hotel_boot` owned by `git` | Created, then dropped at the end |
| `php artisan migrate --force` | All five migrations passed, including `2026_10_15_100000_create_hotel_domain_tables` |
| `php artisan config:cache` and `php artisan view:cache` | Passed. Both caches were cleared afterward. `bootstrap/cache/config.php` is absent. |
| `php artisan about --only=environment` | Environment `production`, debug mode off |
| HTTP `GET /api/v1/health` through the Laravel kernel | `200` and `{"data":{"status":"ok","database":"ok"}}` |
| `pg_dump` of `git_hotel`, restore into `git_hotel_restore` | Restored copy had 3 hotels. Dump file deleted. Source `git_hotel` still has 3 hotels. |
| `php artisan migrate:rollback --step=1` on `git_hotel_boot` | `hotels` was gone (`to_regclass` returned null) |
| `php artisan migrate --force` again | `hotels` existed again |

## Not run

- Render deploy and an HTTPS check of the public URL. No Render CLI or API key is present on this machine.
- A concurrent two-request race against `lockForUpdate`.
- `npm run build` inside the Docker image on this machine. The web build command itself succeeded.
- Email, SMS, WhatsApp, or a payment provider. Those integrations are not in the app.
