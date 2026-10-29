---
status: accepted
---

# LoRa payloads are end-to-end encrypted and the gateway is untrusted

A LoRa node encrypts its payload with AES-128-CTR under a per-device key (`lora_aes_key`) known only to the node and the backend, so a compromised gateway reads nothing. LoRa traffic has its own webhook (`/api/v1/lora/webhook`), kept apart from the trusted Wi-Fi JSON path. Replay is blocked with a frame counter: a counter at or below the stored value is rejected. Jumps of up to 10,000 are accepted on purpose, because the firmware saves the counter to flash only occasionally and skips ahead after a power loss.
