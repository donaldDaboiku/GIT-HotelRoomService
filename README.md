# GIT Hotel Room Service

Guest room service for hotels, built by GreenPasture Integrated Tech System. Guests open a room QR link. Staff process requests in a browser. No guest account and no installed app.

## Layout

| Path | What it is |
| --- | --- |
| `api/` | Laravel JSON API and PostgreSQL |
| `web/` | React guest, staff, admin, and platform screens |
| `docs/` | Architecture, database, API, setup, deployment, and backup |

Start with [docs/SETUP.md](docs/SETUP.md). The design is in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Local demo

After setup and `php artisan db:seed`, sign in at http://localhost:5173/staff/login. The password is the `SEED_PASSWORD` in `api/.env`.

| Person | Email | Role |
| --- | --- | --- |
| Platform | platform@gitsystem.test | GIT platform administrator |
| Ada Okonkwo | ada.admin@aurora.test | Aurora Suites administrator |
| Tunde Bakare | tunde.reception@aurora.test | Reception |
| Chioma Eze | chioma.housekeeping@aurora.test | Housekeeping |
| Ibrahim Musa | ibrahim.kitchen@aurora.test | Kitchen |
| Ngozi Adeyemi | ngozi.maintenance@aurora.test | Maintenance |
| Harbour Admin | admin@harbour.test | Second hotel, used to prove isolation |

Room links are printed by `php artisan hotel:links`.
