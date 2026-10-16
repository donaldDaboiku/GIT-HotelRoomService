# Local setup

PHP 8.4, Composer, Node 22 or newer, and PostgreSQL 16 or newer. This machine uses PostgreSQL on port **5433** because port 5432 is a separate cluster.

## Database

With Docker:

```bash
docker compose up -d
docker compose exec postgres psql -U git -d git_hotel -c "CREATE DATABASE git_hotel_test OWNER git;"
```

Without Docker, create the role and both databases on the server listening on 5433:

```sql
CREATE ROLE git LOGIN PASSWORD 'git_local_dev';
CREATE DATABASE git_hotel OWNER git;
CREATE DATABASE git_hotel_test OWNER git;
```

`git_local_dev` is the local development password. Use a different password outside this machine and put it only in `.env`.

## API

```bash
cd api
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
```

Set `SEED_PASSWORD` in `.env` to at least 10 characters, then:

```bash
php artisan db:seed
php artisan hotel:links
php artisan serve
```

The API listens on http://127.0.0.1:8000. `QUEUE_CONNECTION=sync` stores in-app notifications during the request. No queue worker is required.

## Web

```bash
cd web
npm install
npm run dev
```

Open http://localhost:5173. Vite proxies `/api` and `/storage` to the Laravel server. Leave `VITE_API_URL` empty for that proxy. Set it to the public API origin when the two apps are hosted on different domains.

## Environment

| Variable | Purpose |
| --- | --- |
| `APP_KEY` | Laravel encryption key. Generated locally. Never commit `.env`. |
| `APP_URL` | API origin used in public file URLs. |
| `DB_*` | PostgreSQL connection. Tests use `git_hotel_test` from `phpunit.xml`. |
| `FRONTEND_URL` | Origin encoded in room QR codes. |
| `CORS_ALLOWED_ORIGINS` | Browser origins allowed to call the API. |
| `GUEST_SESSION_DAYS` | Lifetime of a guest room session. |
| `SEED_PASSWORD` | Password for demo accounts. Required only by the seeder. |

`APP_DEBUG` must be `false` in production.

## Tests

```bash
cd api && php artisan test
cd web && npm test && npx tsc -b
```

## Troubleshooting

- Guest page says the room link was not found: the QR token is revoked, the room or hotel is inactive, or `FRONTEND_URL` does not match the site you opened.
- Staff sign-in says the hotel is not active: a platform administrator suspended it.
- Images 404: run `php artisan storage:link`.
- Port 5432 password prompt: that is the other PostgreSQL service. Point `DB_PORT` at 5433 or the Docker mapping.
- Login works once and then every later test user looks like the first: the test client must forget the auth guard between HTTP calls. `tests/TestCase.php` does this. Production PHP starts a new request each time.
