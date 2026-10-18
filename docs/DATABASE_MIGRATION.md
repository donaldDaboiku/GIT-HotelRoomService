# Database migration

## Supported database

PostgreSQL is the supported database. The domain migration adds `CHECK` constraints with `ALTER TABLE ... ADD CONSTRAINT`, which PostgreSQL accepts and SQLite does not. `config/database.php` defaults to `pgsql`. `docker/start.sh` sets `DB_CONNECTION=pgsql` and exits if `DB_URL` is empty.

SQLite is not a fallback. A previous Render deploy failed because the container had no `DATABASE_URL` and Laravel opened `api/database/database.sqlite`.

## What was validated

On 16 October 2026, PostgreSQL 18.2 was started on `127.0.0.1:5433`. PHPUnit uses database `git_hotel_test` (`api/phpunit.xml`) and `RefreshDatabase`, which migrates that database from empty for the suite.

Command:

```bash
cd api
php artisan test
```

Result after the server was accepting connections: 16 tests, 97 assertions, passed.

On 17 October 2026 the same migrations were applied with `php artisan migrate --force` to an empty database, `git_hotel_boot`, while `APP_ENV=production` and `APP_DEBUG=false`. `migrate:rollback --step=1` removed `hotels`. Running `migrate --force` again created it. Both drill databases were dropped. `git_hotel` was not rolled back.

That PHPUnit run applies:

- `2026_10_15_093842_create_personal_access_tokens_table`
- `2026_10_15_100000_create_hotel_domain_tables`

`migrate:fresh` was not run against `git_hotel`. That database holds the local demo hotel.

## Production steps

1. Create an empty PostgreSQL database and a role that can use only that database.
2. Set `DB_URL` or `DATABASE_URL` to that database. On Render, connect the database so `DATABASE_URL` is injected. `docker/start.sh` copies it to `DB_URL`.
3. Set `APP_KEY` before the first boot.
4. Start the container. It runs `php artisan migrate --force`.
5. Confirm `GET /api/v1/health` returns `"database":"ok"`.

Do not point production at the local role `git` or the password in `.env.example`.

## Rollback

`php artisan migrate:rollback` drops the domain tables created by the single hotel migration. That deletes hotels, rooms, requests, and users. Use it only on an empty or disposable database.

For a database that already has hotel data:

1. Stop the app so it stops writing.
2. Restore the `pg_dump` custom archive described in `docs/BACKUP.md`.
3. Restore `storage/app/private` and `storage/app/public`.
4. Deploy the previous image if the failure was caused by a schema change.

This audit did not take or restore a production dump.

## Data already in git

`api/database/database.sqlite` was committed before SQLite support was removed. This working tree stops tracking it. History on GitHub still contains that blob until a commit that removes it is pushed. Do not use that file for production data.
