# Security audit

Date: 16 October 2026. Method: code review of authentication, tenant scope, uploads, QR output, headers, and secrets, plus the tests listed in `docs/TEST_REPORT.md`.

## Controls that are in place

- Staff login uses Laravel Sanctum bearer tokens with an expiry. Logout deletes the current token. `EnsureHotelStaff` rejects a token when the user or hotel is not active. A new test covers a suspended account.
- Login is limited to 5 requests per minute. Guest session, submit, and read routes have their own limiters in `AppServiceProvider`.
- Guest tokens are random, stored as SHA-256 hashes, and bound to one hotel and room. Revoked QR codes cannot open a session.
- Queries that load rooms, requests, users, and attachments check `hotel_id` on the server. A feature test shows Hotel A cannot read Hotel B’s room history.
- Order totals are calculated on the server in kobo. Guest payloads cannot set prices or status.
- Uploads allow JPEG, PNG, and WebP, check `getimagesize`, reject `.php` names, and use a generated name. Private files use the `local` disk with `serve` set to false. Downloads check the guest token or staff access.
- CORS origins come from `CORS_ALLOWED_ORIGINS`. `supports_credentials` is false, so a wildcard-with-credentials configuration is not used.
- `APP_DEBUG` defaults to false in `config/app.php`. The example file is a local template and says production must set `APP_DEBUG=false`.
- QR URLs contain public room codes, not passwords or tokens.
- Audit rows are written for status changes and assignments.
- Online payment cannot be turned on through the settings endpoint. Notification rows only allow the `in_app` channel.

## Changes made in this audit

- `SecurityHeaders` now sets `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'`, `Permissions-Policy`, and HSTS when the request is secure.
- `docker/nginx.conf` sets the page policy used by the React app, including Google fonts and blob images. HSTS is empty unless `X-Forwarded-Proto` is `https`.
- The room print page no longer uses `dangerouslySetInnerHTML`. The SVG is shown through an object URL on an `img`.
- Status and assignment writes lock the request row and check the status again inside the transaction.
- `api/database/database.sqlite` is staged for removal and ignored. It is also listed in `.dockerignore`.

## Unresolved issues

1. **Public Render host is not redeployed.** Locally, production mode booted on a clean PostgreSQL database with the real application key, and `/api/v1/health` returned 200. `docker/start.sh` now stops when `APP_KEY` is missing. The Render service still needs `DATABASE_URL`, that key, and a new deploy. This machine cannot sign in to Render.
2. **Tokens in `localStorage`.** This matches the current bearer design. A content security policy reduces script injection. It does not remove the risk if a script is allowed later. Moving to HTTP-only cookies needs a planned change to Sanctum’s SPA cookie flow, CSRF, and the guest header. That migration was not started.
3. **SQLite file still on `origin/main`.** The deletion is local until it is committed and pushed.
4. **No self-service password reset.** There is no mail provider. Hotel administrators can set a new staff password. Rate limiting a reset endpoint does not apply because the endpoint does not exist.
5. **HSTS and the page content security policy** are in the image config and were not observed on a live HTTPS response.
6. **Backups, alerting, and rollback** are documented and were not executed against production.
7. **External email, SMS, WhatsApp, and card payments** are not implemented. Do not tell a hotel those channels are live.

## Tests that cover the security changes

- `test_api_sets_security_headers`
- `test_suspended_staff_cannot_use_an_existing_token`
- Existing isolation, revoked QR, upload, duplicate submit, and logout tests

All 16 API tests passed on 16 October 2026. See `docs/TEST_REPORT.md`.
