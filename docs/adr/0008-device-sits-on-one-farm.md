---
status: accepted
---

# A device sits on one farm, and its sensors take the farm from it

A device is placed on at most one farm (`devices.farm_id`, null while it is unassigned). Sensors have no farm of their own: a sensor's farm is its device's farm. Sensors used to carry `farm_id`, from when the device was seen as plumbing for its sensors. The device has since become the entity the platform runs on: it authenticates (ADR-0004), goes online and offline, reports battery (ADR-0006), and Farm Health and alert rules count devices. Its sensors hang off it by cable, within metres of it. With the farm on the sensor, one device's sensors could sit on two farms, a diagnostic sensor needed a farm only so its device would show up, and a device with no sensors on a farm did not count toward that farm's health. The platform's auxiliary entity is the gateway, which belongs to a tenant and not to a farm.

## Considered options

- **Farm on both device and sensor, kept in sync**, the way every row carries `user_id` (ADR-0003). Rejected: a device never changes owner, but it does move between farms. Every move would have to rewrite its sensors, and one missed write would bring back the split state without any error.

## Readings keep the farm they were taken on

A reading belongs to the farm its device was on when the reading was taken. Ingestion writes that farm as the `farm_id` tag on the InfluxDB point, read from the device when the reading arrives, not when the queued write runs. A farm's history (stats and charts) filters on that tag. Its current state (Farm Health, sensor list, latest values, map) comes from the devices on it now. Moving a device leaves its earlier readings with the old farm, deleting a device leaves its readings in the farm's history, and a sensor's own history covers every farm it has been on.

## Consequences

- A device cannot span two farms. A device whose probes reach across a boundary belongs to one farm, with zones (#6) for placement inside it.
- The farm is set only when a device is registered or edited, never from a sensor form or a QR scan.
- Deleting a farm unassigns its devices rather than deleting them.
- Readings taken while a device is unassigned belong to no farm. Placing the device later does not claim them.
- If a wrong assignment is corrected later, the readings in between stay on the wrong farm. InfluxDB cannot change the tags of stored points, and there is no correction tool.
