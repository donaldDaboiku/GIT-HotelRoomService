# Backup and restore

Back up the PostgreSQL database and the two storage trees. A database dump without the files loses logos, menu images, and fault photos.

## What to copy

| Path | Contents |
| --- | --- |
| Database `git_hotel` | Hotels, users, requests, money, audit rows |
| `api/storage/app/private` | Guest fault photos. Not public. |
| `api/storage/app/public` | Logos and menu images, served through `public/storage` |
| `api/.env` | Stored with the secret backup, not in git |

## Dump

```bash
pg_dump --host=127.0.0.1 --port=5433 --username=git --format=custom --file=git_hotel.dump git_hotel
```

Use the production host, port, and role in place of the local values. Store the dump off the application server.

## Restore

Stop the API so requests are not written during the restore.

```bash
createdb --host=127.0.0.1 --port=5433 --username=git git_hotel
pg_restore --host=127.0.0.1 --port=5433 --username=git --dbname=git_hotel --clean --if-exists git_hotel.dump
```

Copy `storage/app/private` and `storage/app/public` back to the same paths, then run `php artisan storage:link` if `public/storage` is missing.

Confirm `APP_KEY` matches the environment that encrypted any framework data. Guest and staff tokens are hashes and random strings; they do not depend on `APP_KEY`, but changing the key invalidates encrypted Laravel payloads such as cookies.

## After restore

1. `php artisan migrate --force` if the dump is behind the current code.
2. Open one known room link and one staff account.
3. Confirm a private attachment downloads only for staff at that hotel.

Test restores on a copy. Do not restore a production dump over the live database without a fresh dump taken immediately before.

On 17 October 2026 this restore was executed locally. `pg_dump` of `git_hotel` was restored into an empty database, `git_hotel_restore`. That copy contained 3 hotels. The copy and the dump file were then removed. The source database was left unchanged.
