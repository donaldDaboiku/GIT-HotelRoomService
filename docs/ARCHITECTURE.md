# GIT Hotel Room Service — Architecture

GreenPasture Integrated Tech System (GIT System) guest room-service application. The workspace was empty, so this is a new two-application repository. No existing code was replaced.

## Applications

| Path | Role |
| --- | --- |
| `api/` | Laravel 13 JSON API, PostgreSQL, Sanctum bearer tokens for staff |
| `web/` | React 19 + TypeScript + Vite guest, staff, admin, and platform UI |
| `docs/` | Architecture, data model, API, setup, deployment, and backup |

The browser never talks to PostgreSQL. All prices, totals, status changes, and permissions are enforced in the API.

## Request flow

1. An administrator creates a hotel, rooms, services, and menu items.
2. Each active room has one active unguessable QR token. The printed code opens `/h/{hotelPublicCode}/r/{roomPublicCode}` on the web app.
3. The web app exchanges that pair for an opaque guest session token (SHA-256 hash stored, raw token returned once).
4. Guests submit housekeeping, maintenance, or food orders. The API loads prices and writes the request, line items, and the first status history row in one database transaction.
5. Staff sign in with Sanctum. They only receive rows for their hotel, role, and department.
6. Status changes go through an allow-list. Each change stores the staff user and a timestamp.
7. The same guest session can list and open only its own requests.

## Tenancy

Hotel-owned tables include `hotel_id`. Staff tokens resolve a user, and queries are constrained to that `hotel_id`. Platform administrators use separate routes and receive hotel status and usage counts, not guest notes or request bodies. A suspended or deactivated hotel cannot accept guest requests or staff sign-in.

## Money

Amounts are integer minor units (kobo for NGN). Service charge and tax are integer basis points. The API applies half-up integer arithmetic. Clients may display menu prices, but order creation ignores any price or total in the payload.

## Payments

Initial methods are cash at delivery and pay at reception. Online payment stays unavailable until a provider is configured for that hotel. Order value, recorded payments, and refunds are stored and reported separately. An unpaid order is not treated as revenue.

## Notifications

In-app notifications are written when a request is created, assigned, escalated, or closed. Email, SMS, and WhatsApp preferences can be stored. Those channels are not marked as sent, because no provider is connected.

## Authentication

- Staff and administrators: `Authorization: Bearer` Sanctum personal access tokens, 12-hour expiry.
- Guests: `X-Guest-Token`, random 32-byte token, stored only as a SHA-256 hash, scoped to one hotel room.
- Guest request URLs use a human reference (`RS-XXXXXXXX`), not a numeric id. Possession of a reference without the guest token is not access.
- CSRF applies to cookie session routes. The API uses bearer and guest tokens, not cookies, so CSRF is not the control on `/api/*`.

## Local data

PostgreSQL on port `5433`, database `git_hotel`, role `git`. Port `5432` on this machine is a separate PostgreSQL service and is not used. `docker-compose.yml` publishes the same port and credentials when Docker is available.
