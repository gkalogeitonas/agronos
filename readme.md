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

