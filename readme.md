# Agronos — Smart Agriculture IoT Platform

A platform for collecting, storing and visualizing sensor data from agricultural devices. Agronos provides device registration, time-series ingestion (InfluxDB), multi-tenant data separation, and a REST API for accessing farms, devices and sensors.

Key features
- Device registration and lifecycle management
- Time-series storage and query (InfluxDB integration)
- Multi-tenant data isolation via global scopes and traits
- HTTP and MQTT device integration patterns
- API resources with policies and form requests

Tech stack
- Backend: Laravel (PHP)
- Time-series DB: InfluxDB
- Relational DB: SQLite/MySQL/Postgres (configurable)
- Frontend: Vite + Vue (ShadCN components preferred)
- Testing: Pest PHP
- Containerization: Docker / docker-compose


Quickstart (Linux)
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

Project structure (high level)
- app/ — Laravel application code (Models, Http controllers, Policies, Services)
- config/ — Environment and service configuration
- database/ — Migrations, factories, seeders
- docs/ — Project docs (PRD, technical reference, guidelines)
- resources/ — Frontend assets and views
- routes/ — API and web routes
- tests/ — Pest test suites

Device onboarding, registration and data flow

- Device provisioning / pre-provisioning: Devices are provisioned server-side (via admin UI or API) and each Device record contains a UUID and secret. The device vendor or operator provides these credentials to the physical device (printed on a label or embedded in a QR code).
- Linking in the app (user action): the user adds the device to their account by copying the UUID/secret or scanning the device QR code in the frontend UI. This links the existing Device record to the user's tenant and can enable the device for operation.
- Enabling the device: once the device record is linked and enabled through the UI, the physical device can authenticate to the platform.

Authentication (device login)

- Endpoint: POST /api/v1/device/login
- Payload:
  {
    "uuid": "device-uuid-string",
    "secret": "device-secret"
  }
- Behavior: the server verifies credentials (see `App\Http\Controllers\Api\V1\DeviceAuthController`). On success the device status is set to ONLINE, `last_seen_at` is updated and a Laravel Sanctum personal access token is issued to the device for future requests.
- Response example:
  {
    "token": "plain-text-token"
  }

Sending sensor measurements (HTTP)

- Auth: use the Sanctum token as a Bearer token in Authorization header for subsequent requests to the data endpoint.
- Endpoint: POST /api/v1/device/data
- Payload shape:
  {
    "sensors": [
      { "uuid": "sensor-uuid-1", "value": 23.5 },
      { "uuid": "sensor-uuid-2", "value": 1012 }
    ]
  }
- Behavior: the `DeviceDataController` validates the payload, updates the device `status` and `last_seen_at`, then delegates to `SensorDataService::processSensorData` which:
  - Resolves sensors for the device and tenant
  - Writes measurements to InfluxDB using `SensorMeasurementPayloadFactory` (fields: value, tags: user/farm/sensor ids and type, time)
  - Updates `last_reading` and `last_reading_at` on the Sensor models
- Example success response:
  {
    "message": "Data received."
  }
- If any sensor UUIDs are unknown for the device, response will include `missing_uuids` with the list of those UUIDs.

InfluxDB payload details

Payloads written to InfluxDB use the following structure (see `SensorMeasurementPayloadFactory`):
- name: sensor_measurement
- tags: user_id, farm_id, sensor_id, sensor_type
- fields: value (float)
- time: server timestamp (seconds precision)


Best practices
- Provision device UUIDs and secrets securely; avoid embedding secrets in firmware without a secure element.
- Clock drift: server timestamp is used for measurements. If the device must provide timestamps, ensure they are validated and timezone-aware.
- Monitor `missing_uuids` responses to detect sensors that were removed or not yet provisioned.

See `docs/Device_registration.md` and `docs/technical_reference.md` for more details and examples.

