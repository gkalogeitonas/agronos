---
status: accepted
---

# Battery level is reported as a sensor, not a device field

A device sends its battery level as a reading from a `battery` sensor, not as device metadata, so ingestion treats every value the same way. Battery is a diagnostic sensor, so farm screens filter it out (`SensorType::farmRelevantValues`). Ingestion also copies the latest battery reading into `devices.battery_level`, a denormalised cache like `Sensor.last_reading`, so low-battery checks stay a plain query on devices. The battery sensor is the source of truth; the column is only its latest value.
