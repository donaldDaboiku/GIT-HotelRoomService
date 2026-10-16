# Database

PostgreSQL. Money is `bigint` minor units (kobo). Rates are basis points from 0 to 10000. Every hotel-owned row has `hotel_id` except rows that belong to a parent which already has it and are still stamped with `hotel_id` where listed below. `personal_access_tokens` is the Sanctum table and is not hotel-scoped; the token belongs to a user, and the user belongs to one hotel or to the platform.

## Tables

| Table | Purpose | Belongs to |
| --- | --- | --- |
| `hotels` | Hotel profile, public code, currency, time zone, status | Platform registry |
| `hotel_settings` | Guest copy, rates, hours, payment flags, notification preferences | One hotel |
| `hotel_subscriptions` | Plan and subscription status | One hotel |
| `response_targets` | Expected response minutes for service, order, and maintenance | One hotel |
| `users` | Staff and platform accounts. `hotel_id` is null only for a platform administrator | Hotel or platform |
| `departments` | Reception, housekeeping, laundry, kitchen, maintenance | Hotel |
| `rooms` | Room number, unique per hotel, active flag | Hotel |
| `room_qr_tokens` | Unguessable public code. One active token per room | Hotel and room |
| `service_categories` | Guest service grouping and kind | Hotel |
| `services` | A requestable service, fee, and department | Hotel |
| `menu_categories` | Menu grouping | Hotel |
| `menu_items` | Price, image, availability, sold-out flag | Hotel |
| `guest_access_tokens` | SHA-256 of the guest session, scoped to one room | Hotel and room |
| `requests` | Service, order, or maintenance request, totals, payment status | Hotel, room, guest token |
| `request_items` | Snapshot of name and price at submission | Request and hotel |
| `request_assignments` | Department and optional staff member | Request |
| `request_status_histories` | From/to status, staff user, time, optional guest-visible note | Request |
| `request_internal_notes` | Staff notes. Guest visibility is a flag, default false | Request |
| `guest_feedback` | One rating row per request | Request |
| `notifications` | In-app staff notices. Channel is constrained to `in_app` and delivery to `stored` | User |
| `attachments` | Private fault photos | Request |
| `audit_logs` | Sensitive staff and admin actions | Hotel when the action is hotel-scoped |

Laravel also creates `cache`, `jobs`, and `personal_access_tokens`.

## Relationships

- A hotel has one settings row, one subscription, many departments, rooms, users, services, menu rows, requests, and response targets.
- A room has many QR tokens and many requests. A partial unique index allows only one `active` token per room.
- A request has many items, assignments, status history rows, notes, and attachments, and at most one feedback row.
- A guest token has many requests. Guest queries filter on `guest_access_token_id`, so a reference alone is not authorization.
- Staff queries add `hotel_id` from the signed-in user. Kitchen staff are further limited to `type = order`. Service staff are limited to non-orders in their department.
- Deleting a hotel cascades through settings, departments, rooms, and QR tokens. Requests restrict hotel deletion while history exists.

## Indexes and constraints

Unique: hotel public code, room number per hotel, department code per hotel, QR public code, request reference, guest token hash, one feedback row per request.

Indexes used by the desk: `(hotel_id, status, created_at)`, `(hotel_id, department_id, status)`, `(hotel_id, room_id)`, and guest token plus creation time.

Check constraints limit status, role, payment status, rating, quantity, basis points, and notification channel. The notification check is what keeps email, SMS, and WhatsApp from being recorded as delivered.
