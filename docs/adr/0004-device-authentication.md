---
status: accepted
---

# Devices authenticate with a QR-delivered secret, then a Sanctum token

Each device ships with a UUID and secret, delivered to the user out of band on its QR label. The user claims the device in the web UI. The device then logs in with UUID and secret to get a Sanctum token, uses that token for HTTP readings, and uses it to fetch its own EMQX credentials for MQTT. The secret stays hashed. This keeps provisioning to "scan and power on", with no per-device setup on the broker by hand.

## Claiming is first come, first served

The UUID is an identifier, not a secret. It is generated on the device or at production time, never by Agronos, and it is globally unique (`unique:devices,uuid`, `unique:sensors,uuid`) because the device API looks devices up without a signed-in user. Whoever claims a UUID first owns the device and sets its hashed secret. Nobody can claim it afterwards, so a second user needs the first to delete it.

Shipped devices use random UUIDs, so they cannot be guessed. This is a deliberate choice, not a bug: do not add a pre-provisioned secret check to claiming, and do not make uniqueness per tenant. The project is open source, so someone flashing their own device can pick any UUID, including a guessable one. If someone else claims it first, that is on them.

A "UUID already exists" message tells a user that some tenant owns that UUID, and nothing more. It is accepted for `store` and device registration. `sensors.scan` uses a neutral message instead. Don't treat either as a tenant data leak.
