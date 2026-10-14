# Redesign follow-ups

The "Field instrument" redesign (branch `feature/template-redesign`) shows several features the backend does not support yet. They render with placeholder data so the design can be reviewed as a whole. Each placeholder is exported from [`resources/js/lib/sampleData.ts`](../resources/js/lib/sampleData.ts), and every page that uses one shows a **SAMPLE DATA** pill.

Each task below lists the screens affected and the `sampleData.ts` exports to remove when it is done. Once every export is gone, delete `sampleData.ts` and the `sampleData` prop on `PageHeader`.

Design reference: the "Agronos Design Template" artifact (artboards named in each task).

---

## 1. Alerts

**Problem.** The Overview, Farm detail and Alerts pages show example alerts. Nothing raises, stores or resolves an alert.

**Scope**
- An `Alert` model (tenant-scoped like `Farm`/`Device`) with severity (`danger`, `warning`, `info`), title, subject (farm/device/sensor), opened and resolved timestamps.
- Rules that open alerts: soil moisture below a threshold (see task 5), device stopped reporting (see task 9), battery below 20% (`LOW_BATTERY_PERCENT` in `lib/format.ts`).
- Feed open alerts to the Overview, Farm detail (that farm only) and Alerts pages, and the "Open alerts" counts on the Overview tile and Farms list.

**Screens:** Overview, Farm detail, Farms list, Alerts (`/alerts`).
**Remove:** `sampleAlerts`, `sampleOpenAlertsForFarm`.
**Artboards:** Overview, Farm detail.

## 2. Actuators and irrigation valve control

**Problem.** The Actuators page and the irrigation valve card on Device detail are visual only; their controls are disabled.

**Scope**
- An actuator model linked to a device and farm (kind, state, last run).
- Commands to open/close or run for a duration, delivered to the device (MQTT for Wi-Fi devices; downlink for LoRa).
- Optional schedules.
- Replace the placeholder route in `routes/web.php` (`actuators.index`) with a controller.

**Screens:** Actuators (`/actuators`), Device detail (valve card). The sidebar entry carries a PROPOSED badge until this ships.
**Remove:** `sampleActuators`.
**Artboards:** Device detail (desktop and mobile).

## 3. Organisation / tenant name

**Problem.** The sidebar footer shows "Your tenant name". Tenancy is per user (`BelongsToTenant` scopes by `user_id`); there is no organisation entity or name.

**Scope:** decide whether a tenant is a user or a new organisation model, then share its name through `HandleInertiaRequests` and use it in `AppSidebar.vue`.

**Remove:** `sampleOrganisation`.

## 4. Farm zones

**Problem.** Farm cards and the Farms list show a fixed zone count. Farms have no zones.

**Scope:** a zone model (polygon inside the farm boundary, name), sensors assigned to a zone, and zone counts on the Overview and Farms list. Per-zone series feed task 5.

**Remove:** `sampleZoneCount`.

## 5. Trends, per-zone charts and irrigation threshold

**Problem.** The reading tiles show example sparklines, and the Farm detail moisture chart shows two example zones and a fixed irrigation threshold.

**Scope**
- Downsampled 24 h series per sensor type for a farm (and later per zone) from InfluxDB, extending `FarmTimeSeriesService`. Load them as deferred props like `timeSeriesStats`.
- A configurable irrigation threshold per farm or zone; also used by the moisture alert rule in task 1.

**Screens:** Farm detail (tiles and chart).
**Remove:** `sampleTrends`, `sampleMoistureSeries`, `sampleIrrigationThreshold`.

## 6. Activity feed

**Problem.** "Recent activity" on the Overview and "Device activity" on Device detail are examples.

**Scope:** record notable events (device registered, sensor added, uplink received, alert opened/resolved, firmware checked) and list the latest per organisation and per device.

**Remove:** `sampleActivity`, `sampleDeviceActivity`.

## 7. Device commands and firmware version

**Problem.** "Restart device" and "Update firmware" on Device detail are disabled, and the firmware version shows `[VERSION]`.

**Scope:** devices report their firmware version (store on `devices`), plus restart and firmware-update commands over the device's channel.

**Remove:** `sampleFirmwareVersion`.

## 8. Signal strength is never stored

**Problem.** `devices.signal_strength` is fillable but nothing writes it, so the Signal column and tile always show "–".

**Scope:** store RSSI (dBm) from LoRa uplinks and Wi-Fi devices when readings arrive. The UI already formats it as `-104 dBm`.

**Screens:** Devices list, Farm detail, Device detail. No sample data to remove.

## 9. Devices never go offline

**Problem.** A device's `status` stays `online` after it stops reporting. Seeded devices show "Online" with a last-seen time of more than 200 days. The derived farm status (`FarmOverviewService`) and the "Devices online" counts inherit this.

**Scope:** a scheduled command that marks devices offline when `last_seen_at` is older than a threshold (per device type or configurable), and back online on the next reading. Optionally raise an alert (task 1).

**Screens:** every page that shows device status or farm status. No sample data to remove.

## 10. Map: farm boundaries on the Overview

**Problem.** The Overview map shows one pin per farm at its centre. The artboard shows farm boundaries.

**Scope:** draw each farm's polygon on `FarmsOverviewMap.vue`, coloured by status, keeping the pins as labels. Farms without a boundary keep only a pin, or none if they have no coordinates.

No sample data to remove.
