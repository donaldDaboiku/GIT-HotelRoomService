# Production readiness audit

Date: 16 October 2026.

Stack inspected: React 19 + TypeScript + Vite 8 in `web/`, Laravel 13 REST API in `api/`, PostgreSQL, Laravel Sanctum bearer tokens for staff, opaque guest tokens, one Docker image for production.

## Release status

**NOT READY FOR PRODUCTION**

The application code for guest ordering, staff workflow, hotel isolation, and PostgreSQL startup is in place. On 17 October 2026 a clean PostgreSQL database was booted with `APP_ENV=production`, `APP_DEBUG=false`, and the existing application key. Migrations, the health check, a backup restore, and a migration rollback were executed against that database. The public Render service was not deployed from this machine: the Render CLI and API key are not available here.

## What was inspected

- Routes in `api/routes/api.php` and `web/src/App.tsx`.
- Auth middleware, guest tokens, tenant checks, uploads, QR printing, status workflow, CORS, and security headers.
- Migrations, `render.yaml`, `Dockerfile`, `docker/start.sh`, and `docker/nginx.conf`.
- Existing tests, `.env.example`, and prior Render failure logs (SQLite used because `DATABASE_URL` was absent).

## Issue register

| ID | Severity | Where | Evidence | Impact | Fix | Test |
| --- | --- | --- | --- | --- | --- | --- |
| PR-01 | High | `api/database/database.sqlite` tracked on `origin/main` | `git ls-files` previously listed the file. Working tree now stages its removal. | A local database file is in git history and the current remote commit. | Stop tracking it. Ignore `*.sqlite*`. Keep it out of the Docker build. | `git status` shows the deletion staged. Not pushed. |
| PR-02 | High | Render service | Last known deploy migrated SQLite and failed on `ADD CONSTRAINT`. | The public site does not start until Postgres and `APP_KEY` are set on Render. | `docker/start.sh` now exits when `DB_URL` or `APP_KEY` is missing. Local production boot against PostgreSQL passed. | Health returned 200 on a clean database. Render itself was not redeployed. |
| PR-03 | High | `web/src/api/client.ts`, guest layout | Staff bearer tokens and guest tokens are stored in `localStorage`. | A script injection on the site can read those tokens. | Content security policy on the pages. Cookie migration was not done; it would replace the current bearer design. | Header test on `/api/v1/health`. Browser login still uses the existing token storage. |
| PR-04 | Medium | `web/src/admin/PrintPage.tsx` | The print page inserted the QR SVG with `dangerouslySetInnerHTML`. | Markup from the SVG response could run in the page. | Render the SVG as an image blob. | Browser: room 101 print card shows the QR image. |
| PR-05 | Medium | API and nginx | API responses had no content security policy or HSTS. | Clickjacking and missing transport policy on the public site. | Headers added in `SecurityHeaders` and `docker/nginx.conf`. | `GET /api/v1/health` returned `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`. HSTS on Render was not observed. |
| PR-06 | Medium | `RequestWorkflow` | Status and assignment updates did not lock the request row. | Two staff updates could interleave. | `lockForUpdate()` inside the existing transactions, then re-check the status. | Existing transition and assignment tests passed after the change. A parallel race test was not added. |
| PR-07 | Medium | `docs/DEPLOYMENT.md` | The guide told operators to run `route:cache`. | That command fails because `routes/web.php` uses a closure. | Document `config:cache` and `view:cache` only, matching `docker/start.sh`. | Not executed as a failing command in this pass. The start script already omits it. |
| PR-08 | Low | Password recovery | No public reset route. Hotel admins reset staff passwords in the dashboard. | Staff who forget a password need an administrator. Mail is not configured, so an email reset was not added. | Left as designed. | Login and logout tests pass. |
| PR-09 | Low | Notifications and payments | In-app notifications only. Online payment stays off. | Guests are not emailed or charged online. | No provider was invented. | Existing order and report tests still pass. |

## Release notes

### Behavior

- QR print cards display the code as an image instead of injecting SVG markup.
- API responses include a content security policy and, on HTTPS, HSTS.
- The Docker site sends browser security headers. HSTS is included only when `X-Forwarded-Proto` is `https`.
- Request status changes and assignments lock the request row before writing.

### Database

- No new migration. PostgreSQL remains the only supported database.
- `api/database/database.sqlite` is removed from the index in this working tree. Commit and push that deletion before the next image build so the remote commit no longer carries the file.

### Compatibility

- Staff and guest authentication is unchanged.
- `VITE_API_URL` stays empty for the same-origin Docker site.
- `/api/v1/health` is unchanged.

## Production release checklist

| Check | Result |
| --- | --- |
| Critical and high code defects fixed or explicitly left with a mitigation | PR-04, PR-05, PR-06, PR-07 fixed. PR-01 fixed locally, not on the remote. PR-02 and PR-03 remain. |
| Authentication and logout | Passed in PHPUnit and in the browser with the Aurora hotel administrator. |
| Hotel isolation | Passed in `test_roles_and_hotels_are_isolated`. |
| Backend tests | 16 passed, 97 assertions, after PostgreSQL on port 5433 was started. |
| Frontend production build | `npm run build` succeeded. |
| Migrations on a clean test database | Passed through PHPUnit `RefreshDatabase` on `git_hotel_test`. |
| Guest and staff workflows | Staff login and QR print verified in the browser. Guest ordering was not repeated in this pass; the existing feature tests cover submission, totals, and privacy. |
| No hardcoded production secrets | `.env.example` uses placeholders. Local demo values are labeled local-only. |
| Production mode, health, backup restore, rollback | Passed on local PostgreSQL 18.2. See `docs/TEST_REPORT.md`. |
| HTTPS and a Render redeploy | Not run. This machine has no Render credentials. |
| Dependency advisories | Composer and npm audits reported none. |

Go/no-go: **no-go** until the blockers in `docs/SECURITY_AUDIT.md` are closed on the host and the SQLite removal is pushed.
