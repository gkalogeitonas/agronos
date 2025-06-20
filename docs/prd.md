# 📌 Product Requirements Document (PRD)

## 🔖 Project Title:

**Smart Agriculture IoT Platform**

## 📄 Overview:

This platform allows users (e.g., gardeners, farmers) to deploy IoT-enabled environmental sensors and actuators in their farms or gardens. Devices may communicate using MQTT or HTTP depending on their connectivity type. The system provides real-time data collection, visualization, and automation control through a web-based Laravel backend.

---

## 🎯 Goals

1. Enable users to deploy, monitor, and manage environmental sensors with minimal technical steps.
2. Provide flexible support for both Wi-Fi and LoRa-based devices.
3. Allow scalable and real-time data collection via MQTT and HTTP.
4. Visualize historical and current environmental conditions.
5. Automate farm actions via actuator control.

---

## 👤 Target Users

* Home gardeners (e.g., backyard users)
* Professional farmers (multi-farm, large-scale agriculture)
* Agricultural researchers

---

## 🧱 Technological Stack Enhancements

* Laravel (latest version)
* MySQL (relational data)
* InfluxDB (time-series data)
* MQTT Broker (e.g., Mosquitto or HiveMQ)
* HTTP POST support for Wi-Fi sensors
* Node.js or Go microservice for MQTT subscription and forwarding

---

## 📦 Key Features

### 1. User Management

* Registration/Login/Logout
* Multi-tenant architecture (each user sees only their own data)

### 2. farm Management

* Create farms with coordinates, size, and crop association

### 3. Crop Management

* Define plant types with optimal thresholds (e.g., moisture range)

### 4. Sensor Management

* Add sensors via QR scan
* Associate sensors with farm and crop
* View real-time/historical data

### 5. Device Management

* Devices are created automatically when the first sensor is registered
* Support Wi-Fi and LoRa devices
* Display status (online/offline), connection type, and location

### 6. Actuator Management

* Manual or rule-based activation (e.g., water pump)

### 7. Data Collection

* ✅ MQTT (1 topic per sensor preferred)
* ✅ HTTP POST (from Wi-Fi sensors)

### 8. Visualization Dashboard

* Live sensor values
* Graphs from InfluxDB (by farm, crop, sensor type)

### 9. Alerts & Notifications

* Notify users via app/email/SMS when values are critical

### 10. Automation Rules

* Define "if-this-then-that" logic for actuators (e.g., moisture < 30%)

---

## 🧾 Supported Device Types

| Device Type  | Connectivity | Sensor Count | Placement | QR Scanning      |
| ------------ | ------------ | ------------ | --------- | ---------------- |
| Wi-Fi Single | Wi-Fi        | 1            | Garden    | Yes              |
| Wi-Fi Multi  | Wi-Fi        | 2+           | Fixed     | Yes (per sensor) |
| LoRa Remote  | LoRa         | 1–4          | farms    | Yes (per sensor) |

---

## 🔗 Sensor-Device Relationship

* One device may contain multiple sensors.
* Each sensor has a unique QR code and is registered individually.
* Sensors are geo-tagged and associated with farms and optionally crops.

---

## 🔐 Security Considerations

* Device authentication via tokens or MQTT credentials
* MQTT credentials can be rotated
* HTTPS enforced for HTTP endpoints
* Topic-based access control in MQTT broker
* Rate limiting and logging for incoming requests

---

## 📊 InfluxDB Data Strategy

**Tags:**

* `user_id`, `farm_id`, `sensor_id`, `sensor_type`, `crop_type`, `location_name`

**farms:**

* Measurements such as `moisture`, `temperature`, etc.

**Timestamps:**

* From device if trusted, or server timestamp fallback

---

## 📊 Database Entities Overview

All core entities contain a direct reference to `user_id`.

* **Users**
* **farms**
* **Devices**
* **Sensors**
* **Actuators**
* **Crops**
* **Sensor Data** (in InfluxDB)
* **Rules**
* **Alerts**

---

## 👣 User Stories

### 🏡 Urban Gardener (Wi-Fi Sensor)

> Maria has a small garden outside her house. She installs a Wi-Fi-based ESP32 sensor with a soil moisture sensor. She scans the QR code from her phone, and the sensor starts sending data via HTTP to the Laravel platform. She sets up an alert when the soil is too dry and gets notified on her dashboard and phone.

**Recommended sensors**:

* Soil Moisture
* Temperature

**Connectivity**:

* Wi-Fi

**Data Flow**:

* Sensor posts via HTTP directly to Laravel

---

### 🌾 Professional Farmer (LoRa Sensor)

> Nikos manages large olive groves. He deploys multiple multi-sensor devices (soil moisture + temperature) connected via LoRaWAN to a central gateway. Each sensor is scanned and registered using its QR code. Sensor data is streamed via MQTT through the LoRa gateway and stored in InfluxDB for long-term analysis and automation of irrigation pumps.

**Recommended sensors**:

* Soil Moisture
* Temperature
* Humidity

**Connectivity**:

* LoRa (via gateway)

**Data Flow**:

* Sensor data pushed via MQTT → Subscriber → Laravel & InfluxDB

---

## 📦 Deliverables

1. Laravel backend with REST API + Web dashboard
2. QR-code registration flow for sensors
3. MQTT & HTTP data ingestion logic
4. InfluxDB integration
5. Vue.js dashboard with charts & maps
6. Actuator control and automation rules
7. Technical documentation

---

