---
status: accepted
---

# Readings live in InfluxDB; MySQL keeps only the latest one

Readings are time series and grow without bound, so they are written to InfluxDB (asynchronously, through a queued job) and all history and statistics queries go there. MySQL holds the relational model, plus a copy of each sensor's latest reading (`last_reading`, `last_reading_at`) so that lists and cards render without querying InfluxDB.
