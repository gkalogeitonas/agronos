---
status: accepted
---

# Devices authenticate with a QR-delivered secret, then a Sanctum token

Each device ships with a UUID and secret, delivered to the user out of band on its QR label. The user claims the device in the web UI. The device then logs in with UUID and secret to get a Sanctum token, uses that token for HTTP readings, and uses it to fetch its own EMQX credentials for MQTT. The secret stays hashed. This keeps provisioning to "scan and power on", with no per-device setup on the broker by hand.
