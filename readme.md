# Agronos — Smart Agriculture IoT Platform

A platform for collecting, storing and visualizing sensor data from agricultural devices. Agronos provides device registration, time-series ingestion (InfluxDB), per-user tenancy, and HTTP, MQTT and LoRa ingestion paths for farms, devices and sensors.

> **Thesis release:** this project started as the thesis *"Σχεδιασμός και Υλοποίηση Πλατφόρμας Έξυπνης Γεωργίας με Ενσωμάτωση IoT Κόμβων"* (Γεώργιος Καλογείτονας, Hellenic Open University, May 2026, supervisor Νικόλαος Σκλάβος). The code as submitted, with the thesis reports, is frozen at the tag [`thesis-v1.0`](https://github.com/gkalogeitonas/agronos/tree/thesis-v1.0) ([release](https://github.com/gkalogeitonas/agronos/releases/tag/thesis-v1.0)). `main` keeps evolving. Current design decisions are in [`docs/adr/`](docs/adr/).

## Key features
- Device registration and lifecycle management
- Time-series storage and query in InfluxDB
- Per-user tenancy for farms, devices and sensors
- Device ingestion over HTTP, MQTT (EMQX) and LoRa (through a gateway)
- Live updates in the UI over websockets (Reverb)

## Tech stack
- Backend: Laravel 13, PHP 8.5
- Frontend: Inertia + Vue, Vite, ShadCN components
- Relational DB: MariaDB
- Time-series DB: InfluxDB (external service, not bundled)
- MQTT broker: EMQX
- Realtime: Laravel Reverb
- Testing: Pest (feature and browser tests)
- Local development: [DDEV](https://ddev.com/). Production: Docker Compose / Dokploy

## Local development (DDEV)

Every command runs inside the DDEV containers: use `ddev artisan`, `ddev composer` and `ddev npm`, never the host's `php`, `composer` or `npm`.

Requirements: [DDEV](https://ddev.readthedocs.io/en/stable/users/install/) and Docker. PHP, Node and the database run in the containers.

```bash
cp .env.example .env               # then fill in INFLUXDB_* (or set INFLUXDB_FAKE=true)
ddev start                         # DDEV sets the DB_* values in .env
ddev composer install
ddev npm install
ddev artisan key:generate
ddev artisan migrate --seed
ddev artisan mqtt:setup-webhook    # EMQX rules for MQTT and LoRa ingestion
ddev composer run dev              # Vite dev server, logs
```

`ddev start` also starts these services, so you don't run them by hand:

| Service | Where |
|---|---|
| App | https://agronos.ddev.site |
| Reverb (websockets) | Port `8080` (`.ddev/config.laravel-reverb.yaml`) |
| Queue worker for `sensor-data,default` | `.ddev/config.laravel-workers.yaml` |
| EMQX broker | MQTT `1883`/`8883`, WebSocket `8083`/`8084`, dashboard http://agronos.ddev.site:18083 |
| phpMyAdmin | https://agronos.ddev.site:8037 |
| Mailpit | https://agronos.ddev.site:8026 |

After seeding, log in as `johndoe@gmail.com` / `password`. The seeded dev devices use the secret `secret`.

Notes:
- **InfluxDB** is not part of DDEV. Point `INFLUXDB_URL`, `INFLUXDB_TOKEN`, `INFLUXDB_ORG` and `INFLUXDB_BUCKET` at an InfluxDB 2 instance, or set `INFLUXDB_FAKE=true` to work without one (no readings are stored).
- **Queue:** readings reach InfluxDB through the `sensor-data` queue. If charts stay empty, check that the DDEV worker is running (`ddev describe`).
- **EMQX rules:** set `EMQX_WEBHOOK_SECRET` in `.env`, then run `ddev artisan mqtt:setup-webhook`. Run it again after resetting the EMQX data volume or changing the secret. Re-running it is safe.
- **Private channels:** if websocket auth fails, check that `REVERB_HOST`/`REVERB_PORT` in `.env` match the DDEV setup. Private channels authenticate through `/broadcasting/auth` with the session cookie.

### Running tests

```bash
ddev artisan test --compact                 # whole suite
ddev artisan test --filter=SensorData       # a subset
ddev browser-tests                          # builds assets, then runs tests/Browser
```

Tests use a fake InfluxDB client (`INFLUXDB_FAKE=true` in `phpunit.xml`), so they don't need a running InfluxDB.

### Simulating devices

`devices:simulate` sends fake, slowly drifting sensor readings for the seeded devices, so you can work on charts, live updates and the ingestion pipeline without real hardware. It refuses to run in production.

```bash
ddev artisan devices:simulate                          # every device with sensors, one round every 10 s, until Ctrl+C
ddev artisan devices:simulate --via=mqtt               # through the EMQX broker, like the firmware
ddev artisan devices:simulate --via=http --once        # one round through the device HTTP API, then exit
ddev artisan devices:simulate --via=mqtt --verify      # one round, then check it reached the database and InfluxDB
ddev artisan devices:simulate Test-Device-1 --interval=5
ddev artisan devices:simulate --help                   # all options
```

How readings are sent (`--via`):

| Mode | Path | Use it to check |
|---|---|---|
| `direct` (default) | Calls `SensorDataService` in-process | Charts, live updates, InfluxDB writes |
| `http` | `POST /api/v1/device/login` → token → `POST /api/v1/device/data` | The device HTTP API |
| `mqtt` | Login → `GET /api/v1/device/mqtt-credentials` → publish to `devices/{uuid}/sensors` → EMQX rule → `/api/v1/device/mqtt-webhook` | The broker, ACL, EMQX rules and webhook |

Before you run it:
- **Seeded devices:** run `ddev artisan db:seed` (or `migrate:fresh --seed`). The seeder gives the dev devices the secret `secret` (`DevEnvironmentSeeder::DEVICE_SECRET`), which `http` and `mqtt` use to log in. A "login failed with HTTP 401" warning usually means the database was seeded before that, and re-seeding fixes it. The seeder only runs in `local` and `testing`.
- **EMQX rules (`mqtt` only):** run `ddev artisan mqtt:setup-webhook` once. Without the rules, publishing succeeds but nothing reaches Laravel. Add `--verify` to find out: it sends one round, waits up to `--verify-timeout` seconds (default 15) and exits non-zero, naming the missing stage, if the readings did not reach the database (check the EMQX rule and webhook) or InfluxDB (check the `sensor-data` queue worker). Run it after changing the EMQX config, the ACL or the rules, and after an EMQX volume reset.

Notes:
- The seeded Test-Device-1 has a `battery` sensor. The simulator drains it slowly (and resets to 100 % at 0) instead of drifting it.
- LoRa devices are skipped with `http` and `mqtt`, because they send through the LoRa gateway. Use `direct` for them.
- `http` and `mqtt` log in as the device, so use the seeded dev devices, not a device a physical board is using.
- The chart for the last 24 h groups readings into 15-minute windows, so a new line appears only after readings in at least two windows.

## Production deployment (Docker Compose)

`docker-compose.yml` runs the whole stack in one go: app (PHP-FPM), web (nginx), two queue workers (`worker` and a dedicated `sensor-worker`), Reverb, EMQX and MariaDB. Code, dependencies and frontend assets are built into the images, so no host PHP or Node is needed. InfluxDB is external: set the `INFLUXDB_*` values.

```bash
cp .env.production.example .env        # fill in every empty value
docker compose run --rm --no-deps app php artisan key:generate --show   # paste into APP_KEY
docker compose up -d --build
docker compose exec app php artisan mqtt:setup-webhook   # EMQX rules, once per EMQX data volume
docker compose exec app php artisan db:seed --force      # optional demo data
```

- The app is served on `WEB_PORT` (default `8000`), and Reverb on `REVERB_PUBLIC_PORT` (default `8080`). Put a TLS reverse proxy in front for production.
- `VITE_*` values are compiled into the frontend, so rebuild (`--build`) after changing them.
- Migrations run automatically every time the `app` container starts.
- MQTT devices connect to ports `1883`/`8883`. The EMQX dashboard is on `18083`.
- To handle more readings, scale the sensor worker: `docker compose up -d --scale sensor-worker=3`.

### Dokploy (shared database)

`docker-compose.dokploy.yml` is an override for a Dokploy server. It disables the bundled MariaDB in favour of a shared one on `dokploy-network`, removes the HTTP host ports (Dokploy's Traefik routes the domains) and keeps only the MQTT ports. Set the Compose service's custom command to:

```
compose -p agronos -f docker-compose.yml -f docker-compose.dokploy.yml up -d --build --remove-orphans
```

Then point `DB_HOST` at the shared MariaDB's internal hostname, and add these domains in the Dokploy UI: `web:80`, `reverb:8080`, `emqx:18083` (dashboard) and `emqx:8083` (MQTT over WebSocket).

## Project structure
- `app/` — Models, controllers, policies, services, jobs and Artisan commands
- `config/` — Environment and service configuration
- `database/` — Migrations, factories, seeders
- `docs/adr/` — Architecture decisions; domain terms are in [`GLOSSARY.md`](GLOSSARY.md)
- `docker/` — Production images and EMQX config
- `resources/js/` — Inertia + Vue frontend
- `routes/` — Web, API and channel routes
- `tests/` — Pest feature, unit and browser tests

## Architecture overview

- **Tenancy:** farms, devices and sensors belong to a user and are filtered by the `BelongsToTenant` trait and `TenantScope` ([ADR-0003](docs/adr/0003-per-user-tenancy.md)). Code that runs without a signed-in user (device API, webhooks, jobs, commands) uses `allTenants()`.
- **Readings:** the API stores the latest value on the sensor and dispatches the `ProcessSensorInfluxData` job on the `sensor-data` queue, which writes to InfluxDB ([ADR-0001](docs/adr/0001-readings-in-influxdb.md)). Failed writes are retried up to 3 times and logged.
- **MQTT:** devices publish to EMQX, and EMQX rules forward the messages to the app's webhooks ([ADR-0002](docs/adr/0002-emqx-webhooks-for-mqtt-ingestion.md)).
- **Device auth:** devices log in with UUID and secret and receive a Sanctum token ([ADR-0004](docs/adr/0004-device-authentication.md)).
- **LoRa:** a LoRa gateway publishes to `lora/+/data`, and EMQX forwards it to `POST /api/v1/lora/webhook`. Payloads are end-to-end encrypted ([ADR-0005](docs/adr/0005-lora-end-to-end-encryption.md)).

InfluxDB points (`SensorMeasurementPayloadFactory`):
- measurement: `sensor_measurement`
- tags: `user_id`, `farm_id`, `sensor_id`, `sensor_type`
- fields: `value` (float)
- time: server timestamp, seconds precision

## Devices and sensors

### Onboarding

- **Devices** are created with a UUID and secret. The operator gives these to the physical device (label or QR code). The user links the device to their account by entering the UUID/secret or scanning the QR code in the UI, and then the device can log in.
- **Sensors** each have their own UUID. On the Add Sensor screen (`resources/js/pages/Sensors/Create.vue`) the user scans the sensor's QR code (UUID, optionally device UUID and type) or types the details in, and can capture GPS coordinates from the phone. The form posts to `sensors.scan` (`SensorController@scan`), which checks with `DevicePolicy` that the user owns the device and then links or creates the sensor.

### Device API

Device firmware: https://github.com/gkalogeitonas/Agronos-iot-device (flashing instructions, hardware notes, telemetry formats).

**Login:** `POST /api/v1/device/login`

```json
{ "uuid": "device-uuid-string", "secret": "device-secret" }
```

On success the device is marked online, `last_seen_at` is updated and a Sanctum token is returned:

```json
{ "token": "plain-text-token" }
```

Send the token as `Authorization: Bearer <token>` on the requests below.

**Send readings over HTTP:** `POST /api/v1/device/data`

```json
{
  "sensors": [
    { "uuid": "Test-Device-1-sensor-1", "value": 22.6 },
    { "uuid": "Test-Device-1-sensor-2", "value": 99.9 }
  ]
}
```

Response: `{ "message": "Data received." }`. If some sensor UUIDs don't belong to the device, the response also includes `missing_uuids`.

**Get MQTT credentials:** `GET /api/v1/device/mqtt-credentials`

```json
{
  "mqtt_broker_url": "https://emqx.local",
  "username": "device-uuid-string",
  "password": "0123ab...",
  "created": true
}
```

`MqttCredentialService` creates an EMQX user on the first call (username = device UUID, random password) and stores the credentials on the device. Later calls return the same credentials with `created: false`. The device then publishes readings to `devices/{uuid}/sensors`, and EMQX forwards them to `POST /api/v1/device/mqtt-webhook`.

The EMQX API settings are under `services.emqx.*` (`EMQX_API_URL`, `MQTT_API_KEY`, `MQTT_API_SECRET`). The app's base URL as seen from EMQX comes from `EMQX_WEBHOOK_BASE_URL` (default `http://web`) or `mqtt:setup-webhook --base-url`. The webhooks only accept requests whose `X-EMQX-Secret` header matches `EMQX_WEBHOOK_SECRET`, and reject everything with 401 while it is empty. `mqtt:setup-webhook` configures the EMQX connectors to send it.
