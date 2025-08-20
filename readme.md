# Agronos — Smart Agriculture IoT Platform

A platform for collecting, storing and visualizing sensor data from agricultural devices. Agronos provides device registration, time-series ingestion (InfluxDB), multi-tenant data separation, and a REST API for accessing farms, devices and sensors.

## Key features
- Device registration and lifecycle management
- Time-series storage and query (InfluxDB integration)
- Multi-tenant data isolation via global scopes and traits
- HTTP and MQTT device integration patterns
- API resources with policies and form requests

## Tech stack
- Backend: Laravel (PHP)
- Time-series DB: InfluxDB
- Relational DB: SQLite/MySQL/Postgres (configurable)
- Frontend: Vite + Vue (ShadCN components preferred)
- Testing: Pest PHP
- Containerization: Docker / docker-compose

## Quickstart (Linux)
1. Requirements
   - PHP 8.x, Composer, Node 18+, npm or pnpm, Docker (optional)
2. Copy environment
   - cp .env.example .env
   - Update `.env` values (DB, INFLUXDB config, APP_KEY)
3. Install dependencies
   - composer install
   - npm install && npm run build
4. Generate app key and run migrations
   - php artisan key:generate
   - php artisan migrate
5. Run (local)
   - php artisan serve 
   - or using Docker: docker-compose up -d --build
6. Run tests
   - php artisan test

## Project structure (high level)
- app/ — Laravel application code (Models, Http controllers, Policies, Services)
- config/ — Environment and service configuration
- database/ — Migrations, factories, seeders
- docs/ — Project docs (PRD, technical reference, guidelines)
- resources/ — Frontend assets and views
- routes/ — API and web routes
- tests/ — Pest test suites

## Device onboarding, registration and data flow

- Device provisioning / pre-provisioning: Devices are provisioned server-side (via admin UI or API) and each Device record contains a UUID and secret. The device vendor or operator provides these credentials to the physical device (printed on a label or embedded in a QR code).
- Linking in the app (user action): the user adds the device to their account by copying the UUID/secret or scanning the device QR code in the frontend UI. This links the existing Device record to the user's tenant and can enable the device for operation.
- Enabling the device: once the device record is linked and enabled through the UI, the physical device can authenticate to the platform.

### Authentication (device login)

- Endpoint: POST /api/v1/device/login

```json
{
  "uuid": "device-uuid-string",
  "secret": "device-secret"
}
```
- Behavior: the server verifies credentials (see `App\Http\Controllers\Api\V1\DeviceAuthController`). On success the device status is set to ONLINE, `last_seen_at` is updated and a Laravel Sanctum personal access token is issued to the device for future requests.

```json
{
  "token": "plain-text-token"
}
```

See `docs/Device_registration.md`.

### Device firmware repository

The device firmware and related embedded code are maintained in a separate repository: https://github.com/gkalogeitonas/Agronos-iot-device

This repo contains the microcontroller firmware, QR/UUID printing utilities and examples for connecting devices to the Agronos platform. Link here for flashing instructions, hardware notes and device-side telemetry formats.

### Sending sensor measurements (HTTP)

- Auth: use the Sanctum token as a Bearer token in Authorization header for subsequent requests to the data endpoint.
- Endpoint: POST /api/v1/device/data
- Payload shape:

```json
{
  "sensors": [
    { "uuid": "Test-Device-1-sensor-1", "value": 22.6 },
    { "uuid": "Test-Device-1-sensor-2", "value": 99.9 },
    { "uuid": "Test-Device-1-sensor-3", "value": 99.9 }
  ]
}
```
- Behavior: the `DeviceDataController` validates the payload, updates the device `status` and `last_seen_at`, then delegates to `SensorDataService::processSensorData` which:
  - Resolves sensors for the device and tenant
  - Writes measurements to InfluxDB using `SensorMeasurementPayloadFactory` (fields: value, tags: user/farm/sensor ids and type, time)
  - Updates `last_reading` and `last_reading_at` on the Sensor models
- Example success response:

```json
{
  "message": "Data received."
}
```
- If any sensor UUIDs are unknown for the device, response will include `missing_uuids` with the list of those UUIDs.

## Sensor onboarding (QR scan and manual)

- Per-sensor UUIDs: each physical sensor attached to a device has its own UUID (in addition to the device UUID). Sensors can expose their UUIDs separately (labels or QR codes) so they can be registered individually.
- QR workflow (recommended for ease):
  1. The operator or vendor pre-provisions the Device and Sensor records server-side (each Sensor has a UUID). A QR code containing the sensor UUID (and optionally device_uuid, type) is printed or displayed on the sensor.
  2. In the frontend the user opens the Add Sensor screen and taps "Scan Sensor QR" (see `resources/js/pages/Sensors/Create.vue`). The app uses the phone camera to read the QR payload.
  3. The front-end will populate the sensor form (uuid, device_uuid, type) and can optionally capture GPS coordinates from the phone to set `lat`/`lon`.
  4. The UI submits to `sensors.scan` which calls `App\Http\Controllers\SensorController@scan`. That action will link the sensor to the selected device and user, update fields if the sensor exists, or create a new Sensor when missing.
- Manual entry: the user may also add a sensor manually using the same Add Sensor form by entering the sensor UUID and other metadata (farm, name, location).
- Backend validation and authorization: `SensorController@scan` validates input (device exists, uuid string) and uses `DevicePolicy` to confirm the user is allowed to link the sensor to the device. New sensors are created with `user_id` and `device_id` set to the current user and device respectively (see `app/Http/Controllers/SensorController.php`).
- Result: after registration the sensor is linked to the user's tenant and will be discoverable in the UI and eligible to receive data from the parent device. When the device sends measurements, `SensorDataService` will resolve sensors by UUID and update `last_reading` / `last_reading_at` on the Sensor models.

See `app/Models/Sensor.php`, `app/Http/Controllers/SensorController.php` and `resources/js/pages/Sensors/Create.vue` for the implementation details and validation rules.

## InfluxDB payload details

Payloads written to InfluxDB use the following structure (see `SensorMeasurementPayloadFactory`):
- name: sensor_measurement
- tags: user_id, farm_id, sensor_id, sensor_type
- fields: value (float)
- time: server timestamp (seconds precision)

## Database schema (summary)

A short overview of the main tables and key columns. See `database/migrations` and `docs/technical_reference.md` for the full schema and migrations.

- users
  - id (PK), name, email, password, email_verified_at, created_at, updated_at
  - relations: hasMany farms, devices, sensors

- farms
  - id (PK), user_id (FK), name, location, size, coordinates (GeoJSON), description, created_at, updated_at
  - relations: belongsTo user, hasMany sensors and are tenant-scoped via the `BelongsToTenant` trait.

- devices
  - id (PK), user_id (FK), name, uuid (unique), secret (hashed), type, status (enum), last_seen_at, battery_level, signal_strength, created_at, updated_at
  - notes: Devices are Authenticatable (Laravel Sanctum tokens).
  - relations: belongsTo user, hasMany sensors

- sensors
  - id (PK), user_id (FK), device_id (FK), farm_id (FK, nullable), crop_id (nullable), name, uuid (unique), type, lat, lon, last_reading (float), last_reading_at (timestamp), created_at, updated_at
  - notes: Sensors are resolved by UUID when ingesting measurements. See `app/Models/Sensor.php` and `app/Http/Controllers/SensorController.php` for registration logic

Notes
- Multi-tenancy: models use a `BelongsToTenant` trait and `TenantScope` to ensure tenant separation; see `app/Traits/BelongsToTenant.php` and `app/Scopes/TenantScope.php`.
- For exact column types, indexes and constraints, consult the migration files in `database/migrations` and the technical reference in `docs/technical_reference.md`.



