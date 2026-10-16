# API

Base path `/api/v1`. JSON in and out. Errors use Laravel’s shape: `message`, and `errors` for validation (HTTP 422). Unknown or cross-hotel records return 404. Forbidden actions return 403.

Authentication:

- Staff: `Authorization: Bearer {token}` from `POST /staff/login`. Tokens expire after 12 hours.
- Guest: `X-Guest-Token` from `POST /guest/sessions`.
- Platform routes accept only `platform_admin`. Hotel admin routes accept only `hotel_admin`. Other staff routes accept an active user of an active hotel.

Prices and totals sent by the browser are rejected, not ignored.

## Health

`GET /health` → `{ "data": { "status": "ok", "database": "ok" } }`. A database failure returns 503 and `status: degraded`.

## Guest

`POST /guest/sessions`

```json
{ "hotel_code": "aurora-lagos", "room_code": "32-character-public-code" }
```

`201` returns `data.token`, `data.expires_at`, `data.hotel`, and `data.room`. Inactive hotels, inactive rooms, and revoked codes return 404.

`GET /guest/context` returns the same hotel and room payload.

`GET /guest/services` returns available services with `fee_minor` and `fee_display`.

`GET /guest/menu` returns categories and available items with server prices.

`POST /guest/requests`

```json
{ "type": "service", "service_id": 1, "quantity": 1, "guest_note": "Extra towels" }
```

`type` is `service` or `maintenance`. Maintenance may include a `photo` file (JPEG, PNG, or WebP, 5 MB) as `multipart/form-data`. `status` and `total_minor` are prohibited.

`POST /guest/orders/quote` and `POST /guest/orders`

```json
{
  "payment_method": "cash_delivery",
  "guest_note": "",
  "items": [{ "menu_item_id": 4, "quantity": 2, "notes": "No pepper" }]
}
```

Quote may omit `payment_method`. The response totals are `subtotal_minor`, `service_charge_minor`, `tax_minor`, and `total_minor`. A created order is status `submitted` and payment status `unpaid`. That is a request, not a paid order.

`GET /guest/requests` and `GET /guest/requests/{reference}` return only rows for the current guest token. The body includes status history, guest-visible messages, totals, and feedback eligibility. Internal notes, priority, and staff names are omitted.

`POST /guest/requests/{reference}/cancel` cancels a `submitted` request.

`POST /guest/requests/{reference}/feedback`

```json
{ "rating": 5, "comment": "Prompt", "category": "service" }
```

Allowed after completion or delivery, once, unless the hotel enables revisions.

A repeat of the same payload within 60 seconds returns 409 with `data.reference` of the existing request.

## Staff

`POST /staff/login` `{ "email", "password" }` → `data.token` and `data.user`.

`POST /staff/logout` revokes the current bearer token. Any authenticated staff or platform token can call it.

`GET /staff/me`

`GET /staff/dashboard` returns counts: `new_requests`, `awaiting_assignment`, `active_tasks`, `overdue`, `completed_today`, `new_orders`, `open_maintenance`.

`GET /staff/departments`

`GET /staff/assignees?department_id=1`

`GET /staff/notifications`

`POST /staff/notifications/{id}/read`

`GET /staff/requests` filters: `search`, `status`, `type`, `department_id`, `priority`, `from`, `to`, `sort` (`newest`, `oldest`, `priority`, `overdue`). Response `data` is the page of rows and `meta` has page counts.

`GET /staff/requests/{id}`

`PATCH /staff/requests/{id}/status` `{ "status", "note", "guest_visible" }`

Reject and cancel require a note of at least 3 characters. The server allow-list depends on role and current status. Kitchen staff cannot move a housekeeping request. Service staff can start or complete a task only when it is assigned to them.

`POST /staff/requests/{id}/assign` `{ "department_id", "user_id" }`

`POST /staff/requests/{id}/notes` `{ "body", "guest_visible" }`

`PATCH /staff/requests/{id}/priority` `{ "priority": "normal" | "high" | "urgent" }`

`POST /staff/requests/{id}/escalate` `{ "note" }` sets priority to urgent and notifies administrators.

`POST /staff/requests/{id}/payment` `{ "payment_status": "unpaid" | "paid" | "refunded" }`

`POST /staff/requests/{id}/items/{item}/unavailable` `{ "note" }` zeroes that line and recalculates the total from the stored rates. Blocked after the request is paid or refunded.

`GET /staff/attachments/{id}` returns the private file for staff who can see that request.

## Hotel administration

All routes require `hotel_admin`.

`GET /admin/hotel` and `PATCH /admin/hotel` with `name`, `contact_phone`, `contact_email`, `address`, `currency`, `timezone`.

`POST /admin/hotel/logo` multipart field `logo`.

`PATCH /admin/settings` updates welcome text, basis points, payment flags, hours, notification preferences, and the three response targets. `allow_online_payment: true` is rejected.

`GET /admin/rooms`, `POST /admin/rooms`, `PATCH /admin/rooms/{id}`

Room body: `number`, `floor`, `room_type`, `is_active`. Creating a room issues a QR token.

`GET /admin/rooms/{id}/history`

`POST /admin/rooms/{id}/qr-code` `{ "action": "issue" | "revoke" | "reissue" }`

`GET /admin/rooms/{id}/qr-code.svg` and `GET /admin/rooms/{id}/qr-code.png`

`GET` and `POST /admin/service-categories`

`GET`, `POST /admin/services`, `PATCH /admin/services/{id}`

`GET` and `POST /admin/menu-categories`

`GET`, `POST /admin/menu-items`, `PATCH /admin/menu-items/{id}`

`POST /admin/menu-items/{id}/image`

`GET`, `POST /admin/staff`, `PATCH /admin/staff/{id}`

`POST /admin/staff/{id}/password` `{ "password" }`

`POST /admin/staff/{id}/status` `{ "status": "active" | "suspended" }`

`GET /admin/audit-logs`

`GET /admin/reports/requests?from=2026-10-01&to=2026-10-15`

The report separates `food_order_value_minor`, `food_payments_received_minor`, `food_refunds_minor`, and `food_unpaid_minor`. Unpaid value is not collected revenue.

`GET /admin/reports/requests.csv` downloads the same range.

## Platform

`GET /platform/health`

`GET /platform/hotels` returns name, public code, status, plan, and counts. It does not return guest requests or notes.

`POST /platform/hotels` creates a hotel, departments, settings, and the first hotel administrator.

`PATCH /platform/hotels/{id}/status` `{ "status": "active" | "suspended" | "deactivated" }`

Suspending or deactivating a hotel deletes that hotel’s staff tokens.

## Limits

Login is limited to 5 attempts a minute. Guest session creation is 20 a minute. Guest submissions are 10 a minute. Guest reads are 120 a minute. Limits are per IP and, for guest routes, the guest token.
