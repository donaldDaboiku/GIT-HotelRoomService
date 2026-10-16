# Deployment

## Render

The guest site and the API are one Docker service. Do not use `npm run dev` or a build command of `yarn`. Those look for a `package.json` in the repository root, and this project does not have one.

1. Commit and push `Dockerfile`, `docker/`, and `render.yaml`.
2. In the Render service, set **Language / Runtime** to **Docker**.
3. Set **Dockerfile Path** to `./Dockerfile` and **Docker Build Context Directory** to `.` (the repository root).
4. Clear the custom **Build Command** and **Start Command**. Docker uses the command in the image.
5. Add a PostgreSQL database in the same region and set `DB_URL` to its internal connection string.
6. Set these environment variables:

| Variable | Value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | output of `php artisan key:generate --show` on your machine |
| `DB_CONNECTION` | `pgsql` |
| `DB_URL` | Render Postgres internal URL |
| `CACHE_STORE` | `file` |
| `SESSION_DRIVER` | `file` |
| `QUEUE_CONNECTION` | `sync` |

`APP_URL`, `FRONTEND_URL`, and `CORS_ALLOWED_ORIGINS` are set from Render’s public URL when the container starts. QR codes use that URL.

The first deploy migrates the database. It does not load the demo hotel. To load the demo once, open the Render shell in the service and run `php artisan db:seed` after setting `SEED_PASSWORD`. Remove that password afterward.

Health check: `GET /api/v1/health`.

## Other hosts

The API and the web app can still be deployed separately. PostgreSQL is not exposed to the browser.

## API

1. Install PHP 8.4 with `pdo_pgsql`, `gd`, and `fileinfo`.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, and a new `APP_KEY`.
3. Set `APP_URL`, `FRONTEND_URL`, and `CORS_ALLOWED_ORIGINS` to the public HTTPS origins.
4. Point `DB_*` at the production database. Do not reuse `git_local_dev`.
5. Run `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, and `php artisan storage:link`.
6. Serve `api/public` through PHP-FPM or another FastCGI host. Do not expose `storage/app/private`. The local disk has `serve` set to false so Laravel will not publish private uploads.
7. Run `php artisan config:cache` and `php artisan route:cache` after the environment is final.

Seed the demo hotel only on a non-production database.

## Web

```bash
cd web
npm ci
npm run build
```

Publish `web/dist`. If the API is on another origin, set `VITE_API_URL` to that origin before the build, for example `https://api.example.com`. Same-origin hosting can keep it empty and reverse-proxy `/api` and `/storage` to Laravel.

QR codes use `FRONTEND_URL`. Reprint room cards after that value changes, or reissue the codes so the printed URL matches the live site.

## Production checks

- HTTPS on both origins.
- `APP_DEBUG=false`.
- Database role limited to the application database.
- Private attachment disk is not web-accessible.
- No `.env` file in the published tree.
- Online payment remains off until a provider is configured. The settings endpoint rejects `allow_online_payment: true`.
- Email, SMS, and WhatsApp preferences are stored only. The app does not claim those messages were sent.

## Health

`GET /api/v1/health` returns the API status without hotel data. Platform administrators also have `GET /api/v1/platform/health` for hotel counts.
