---
status: accepted
---

# Zones hold sensors, by explicit assignment

A farm can be divided into zones: named parts of one farm (`zones.farm_id`) that group some of its sensors. A sensor is in at most one zone (`sensors.zone_id`, null when it is in none). Zones hold sensors, not devices. A device's probes can reach across the farm, and ADR-0008 already says that such a device belongs to one farm, with zones for placement inside it, so its sensors can sit in different zones. A zone is tenant-scoped like Farm, Device and Sensor (ADR-0003) and never moves to another farm. Deleting a zone leaves its sensors on the farm with no zone, and deleting a farm deletes its zones.

## Considered options

- **Zones hold devices**, so a sensor takes its zone from its device, the way it takes its farm (ADR-0008). Rejected: it puts all the probes of one device in the same zone, which is the case zones exist to handle.
- **Zones as polygons, with sensors assigned from their position**. Rejected for now: sensor positions are entered by hand and are often missing or rough, so a sensor near a boundary would land in the wrong zone without anyone choosing it. A sensor is placed in a zone only when a user puts it there. A zone has no polygon; one can be added later for the map without changing how sensors are assigned.

## Readings keep the zone they were taken in

Following ADR-0008, a reading belongs to the zone its sensor was in when it was taken. Ingestion tags the point with that zone, read when the reading arrives. A zone's history filters on that tag, and its current state comes from the sensors in it now. Moving a sensor to another zone, or deleting a zone, leaves earlier readings with the zone they were taken in.
