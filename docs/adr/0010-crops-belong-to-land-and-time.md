---
status: accepted
---

# Crops belong to land and time, and sensors take them from where they stand

What grows on a farm is recorded as a **planting**: one crop on one piece of land (a whole farm, or one zone) for a period, with optional start and end dates. A sensor has no crop of its own. Its crop is its zone's current planting, otherwise its farm's current whole-farm planting, otherwise none. A piece of land has one planting at a time, so two crops side by side are two zones, which is also how Greek parcel declarations work (Α.1161/2026: one crop per parcel). None of the data models or products reviewed in [the crop modelling research](../research/crop-modelling.md) (farmOS, ADAPT, John Deere, LiteFarm, FIWARE, CropX and others) puts the crop on the sensor. They all attach it to land and time, and sensors inherit it.

## Considered options

- **Crop on the sensor**, with an optional farm default. This was the starting idea. Rejected: every probe would have to be told again, and a sensor moved to another zone would keep a crop that is no longer under it.
- **Several concurrent plantings on the same land**, for mixed gardens and intercropping. Rejected: a sensor's crop would become a list that says nothing about what it measures. Mixed gardens use zones where a sensor sits, and the catalogue's generic crops (Vegetable garden, Mixed orchard) everywhere else.
- **Plantings only on zones.** Rejected: a farm with a single crop would then need a zone just to hold it.

## Plantings stay out of InfluxDB

Readings get no crop tag. Unlike a device's farm (ADR-0008), plantings are often entered late or backdated, and a tag on stored points cannot be corrected. What was growing when a reading was taken is derived when needed from the reading's `zone_id` and `farm_id` tags (ADR-0009, ADR-0008) and the planting dates in SQL.

## Catalogue crops are an exception to tenancy

Crops come from a small shared catalogue, which a migration loads on every environment, plus crops a tenant adds for their own use. A catalogue crop has no owner, so `Crop` cannot use `BelongsToTenant` (ADR-0003): the global scope would hide catalogue rows, and its `creating` hook would give them an owner. It has its own scope instead, for "catalogue crops plus the signed-in tenant's", and any crop id from input is validated against that same set. Plantings are tenant-scoped as usual.

## Consequences

- A zone with no current planting shows its farm's crop. Land deliberately left unplanted gets the catalogue crop Fallow, which overrides the farm like any other planting.
- A planting ends when the crop leaves the land, not at harvest. Harvests are not modelled.
- The catalogue holds no agronomic values such as Kc, root depth or moisture thresholds. A moisture threshold depends on soil as much as on crop, so a crop gives no default threshold (section 4 of the research).
- Crop names are English only, like the rest of the UI. Greek names come with the translation of the whole application.
