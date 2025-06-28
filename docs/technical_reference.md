# Agronos Technical Reference

This document serves as a technical reference for the Agronos project, highlighting key components and structures from the predecessor AgroSense project that are being incorporated or adapted. This information is particularly useful for context when working with LLM copilots.

## Project Evolution

AgroSense was initially implemented as:
- Mobile-first Android application using NativeScript + Vue.js
- Laravel REST API backend
- MySQL for data storage

Agronos is the successor, implemented as:
- Web-first application with Laravel + Vue.js + Inertia
- Support for IoT through MQTT and HTTP protocols
- Dual database structure: MySQL for relational data and InfluxDB for time-series data

## Data Models & Relationships

### Core Entities

#### From AgroSense (Original Implementation)

1. **Users**
   - Personal information (name, email)
   - Authentication credentials (password)

2. **Farms/farms**
   - Associated with a user (user_id)
   - Name
   - Geographic boundaries (stored as JSON coordinates)

3. **Sensors**
   - Associated with a user (user_id)
   - Associated with a farm (farm_id)
   - Unique code (for QR scanning)
   - Geographic position (lat/lon)

4. **Measurements**
   - Associated with a sensor (sensor_id)
   - Humidity readings
   - Timestamp of measurement

#### For Agronos (Enhanced Implementation)

1. **Users** - Same as AgroSense with multi-tenant architecture

2. **farms**
   - Enhanced with crop association
   - Size metrics
   - Detailed coordinates

3. **Devices**
   - New entity to represent physical hardware
   - May contain multiple sensors
   - Connectivity type (Wi-Fi, LoRa)
   - Status tracking (online/offline)

4. **Sensors**
   - Associated with a device
   - Associated with a farm
   - Optional crop association
   - Support for sensor naming (e.g., "North farm Moisture")
   - QR code registration support

5. **Actuators**
   - New entity for controlling physical devices (e.g., water pumps)
   - Manual or rule-based activation

6. **Crops**
   - Crop types with optimal thresholds (e.g., moisture range)
   - Used for automated recommendations

7. **Rules**
   - If-this-then-that automation logic for actuators

8. **Alerts**
   - Notification configurations for critical values

## Database Schema

### Relational Database (MySQL/PostgreSQL)

#### Users Table
| Column           | Type       | Description              |
|------------------|------------|--------------------------|
| id               | BIGINT     | Primary key              |
| name             | STRING     | User's name              |
| email            | STRING     | User's email (unique)    |
| password         | STRING     | User's hashed password   |
| email_verified_at| TIMESTAMP  | Email verification time  |
| remember_token   | STRING     | Token for "remember me"  |
| timestamps       | TIMESTAMP  | Created/updated times    |

#### farms Table
| Column      | Type       | Description                     |
|-------------|------------|---------------------------------|
| id          | BIGINT     | Primary key                     |
| user_id     | BIGINT     | Foreign key to `users` table    |
| name        | STRING     | farm name                      |
| coordinates | JSON       | GeoJSON Polygon of the farm area |
| size        | DECIMAL    | Size in hectares/acres          |
| crop_id     | BIGINT     | Optional Foreign key to `crops` table    |
| timestamps  | TIMESTAMP  | Created/updated times           |

- The `coordinates` column now stores a GeoJSON Polygon, e.g.:
  ```json
  {
    "type": "Polygon",
    "coordinates": [
      [
        [23.7275, 37.9838],
        [23.7280, 37.9838],
        [23.7280, 37.9843],
        [23.7275, 37.9843],
        [23.7275, 37.9838]
      ]
    ]
  }
  ```
- This format is compatible with Mapbox, Leaflet, and most geospatial libraries.

#### Devices Table
| Column      | Type       | Description                       |
|-------------|------------|-----------------------------------|
| id          | BIGINT     | Primary key                       |
| user_id     | BIGINT     | Foreign key to `users` table      |
| type        | STRING     | Device type (Wi-Fi/LoRa)          |
| status      | STRING     | Status (online/offline)           |
| location    | JSON       | Device location coordinates       |
| timestamps  | TIMESTAMP  | Created/updated times             |

#### Sensors Table
| Column      | Type       | Description                         |
|-------------|------------|-------------------------------------|
| id          | BIGINT     | Primary key                         |
| user_id     | BIGINT     | Foreign key to `users` table        |
| device_id   | BIGINT     | Foreign key to `devices` table      |
| farm_id    | BIGINT     | Foreign key to `farms` table       |
| crop_id     | BIGINT     | Optional foreign key to `crops`     |
| name        | STRING     | Optional sensor name                |
| code        | STRING     | Unique sensor code (for QR)         |
| type        | STRING     | Sensor type (moisture, temp, etc.)  |
| lat         | DECIMAL    | Latitude of the sensor              |
| lon         | DECIMAL    | Longitude of the sensor             |
| timestamps  | TIMESTAMP  | Created/updated times               |

#### Actuators Table
| Column      | Type       | Description                         |
|-------------|------------|-------------------------------------|
| id          | BIGINT     | Primary key                         |
| user_id     | BIGINT     | Foreign key to `users` table        |
| device_id   | BIGINT     | Foreign key to `devices` table      |
| farm_id    | BIGINT     | Foreign key to `farms` table       |
| name        | STRING     | Actuator name                       |
| type        | STRING     | Actuator type (pump, etc.)          |
| status      | STRING     | Current status (on/off)             |
| timestamps  | TIMESTAMP  | Created/updated times               |

#### Crops Table
| Column          | Type       | Description                      |
|-----------------|------------|----------------------------------|
| id              | BIGINT     | Primary key                      |
| user_id         | BIGINT     | Foreign key to `users` table     |
| name            | STRING     | Crop name                        |
| moisture_min    | DECIMAL    | Minimum moisture threshold       |
| moisture_max    | DECIMAL    | Maximum moisture threshold       |
| timestamps      | TIMESTAMP  | Created/updated times            |


### Time-Series Database (InfluxDB)

Sensor measurements will be stored in InfluxDB with the following structure:

#### Tags (for querying and filtering)
- `user_id` 
- `farm_id`
- `sensor_id`
- `sensor_type`
- `crop_type`
- `location_name`

#### farms (actual measurements)
- `moisture`
- `temperature`
- `humidity`
- `light`
- And other sensor-specific measurements

#### Timestamps
- Preferably from the device if trusted, or server timestamp as fallback

## Authentication & Security

### User Authentication
- Email/password login

### Device Authentication
- Secure token issuance for sensors/devices
- User approval workflow for new device connections
- Token verification for all device communications

### Security Measures
- HTTPS enforced for all HTTP endpoints
- MQTT credentials with topic-based access control
- Rate limiting for incoming requests
- Credential rotation capabilities for MQTT



## Device HTTP Endpoints

### Device Registration & Authentication Flow

1. **User-Initiated Registration** (Browser-based)
   - `POST /devices/register-by-user` - User registers device after scanning QR code
     - Requires authenticated user session
     - Payload: `{ "uuid": "unique-device-id", "secret": "from-qr-code", "name": "My Device", "farm_id": 123, "type": "wifi|lora", "location": {"lat": 37.9838, "lng": 23.7275} }`
     - Response: Device successfully registered confirmation

2. **Device First Communication**
   - `POST /api/devices/register` - First device communication to get auth token
     - Payload: `{ "uuid": "unique-device-id", "secret": "device-secret" }`
     - Response: `{ "device_id": 123, "token": "auth-token-for-future-requests" }`

3. **Device Authentication Renewal**
   - `POST /api/devices/auth` - Authenticate an existing device and get a new token
     - Payload: `{ "uuid": "unique-device-id", "token": "current-token" }`
     - Response: `{ "token": "new-auth-token" }`

### Data Submission (Authenticated Devices)
- `POST /api/devices/data` - Submit sensor readings from a device
  - Headers: `Authorization: Bearer {device-token}`
  - Payload:
    ```json
    {
      "device_id": 123,
      "readings": [
        {
          "sensor_code": "sensor-unique-code",
          "type": "moisture|temperature|humidity|light",
          "value": 42.5,
          "timestamp": "2025-06-19T14:30:00Z" // Optional, server timestamp used if not provided
        }
      ]
    }
    ```

- `POST /api/devices/status` - Update device status information
  - Headers: `Authorization: Bearer {device-token}`
  - Payload: `{ "device_id": 123, "status": "online|offline|error", "battery": 85, "signal": 4 }`




## Technical Implementation

### MQTT Integration

For MQTT-based data collection and device communication:

1. **MQTT Broker Setup**
   - Self-hosted Mosquitto or cloud-based HiveMQ
   - TLS encryption for secure communication
   - Authentication required for all connections

2. **Topic Structure**
   - `user/{user_id}/device/{device_id}/sensor/{sensor_id}` - for sensor data
   - `user/{user_id}/device/{device_id}/actuator/{actuator_id}/command` - for sending commands
   - `user/{user_id}/device/{device_id}/actuator/{actuator_id}/status` - for getting actuator status

3. **Subscription Service**
   - Node.js or Go microservice to subscribe to all topics
   - Processes incoming data and stores it in InfluxDB
   - Executes rule-based automation logic

### HTTP Data Collection

For HTTP-based data collection from Wi-Fi devices:

1. **Device Authentication**
   - Devices authenticate using a unique token
   - Rate limiting to prevent abuse

2. **Data Submission**
   - `POST /api/measurements` endpoint accepts JSON data with:
     - Device identifier
     - Sensor identifier
     - Value(s)
     - Timestamp (optional, server can provide)

### Frontend Implementation

The frontend is based on Laravel’s native Vue starter pack, enhanced with ShadCN components for UI consistency and rapid development.

1. **Dashboard Views**
   - farm overview with maps
   - Device and sensor listings
   - Real-time data visualization
   - Historical data graphs

2. **Management Interfaces**
   - User settings
   - farm management
   - Device and sensor configuration
   - Rule creation and management

3. **Visualization Components**
   - Interactive maps using Leaflet or similar
   - Charts and graphs for sensor data using Chart.js or similar
   - Real-time data updates using WebSockets

## Future Enhancements

Based on AgroSense recommendations for improvements:

1. **Mobile App Integration**
   - Future development of a new mobile application
   - Cross-platform using React Native or Flutter
   - Share core logic and APIs with web application

2. **Advanced Analytics**
   - Machine learning for prediction and optimization
   - Historical trend analysis
   - Crop-specific recommendations

3. **Hardware Expansion**
   - Support for more sensor types
   - Integration with commercial agricultural IoT platforms
   - Weather data integration

---

This technical reference provides a detailed overview of how the Agronos project evolves from and builds upon the AgroSense platform, serving as a valuable resource for LLM copilots working on the Agronos codebase.

