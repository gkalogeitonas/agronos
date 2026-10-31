---
status: accepted
---

# Agronos MQTT topics live under a configurable prefix, and Agronos writes its own per-user ACL rules

The EMQX broker is meant to be shared with other projects, so Agronos must not claim generic topics or own broker-wide config. Every Agronos topic starts with a configurable prefix (`services.emqx.topic_prefix`, default `agronos`): `{prefix}/devices/{uuid}/{sensors|status|commands}` and `{prefix}/lora/{gateway-username}/data`. The `mqtt:setup-webhook` rules forward only those topics to Laravel.

Because the prefix is config, the ACL can't stay in the static `acl.conf`, which can't read it. Instead Agronos writes per-user rules to EMQX's `built_in_database` authorization source through the management API: device rules when it creates the device's MQTT user, and gateway rules when a grower creates a gateway on the Gateways screen. Gateway usernames are generated as `gw-{uuid}`, so they don't collide on the shared broker either. `acl.conf` keeps only generic broker rules and a final deny. We rejected EMQX HTTP authorization against Laravel because it would make every publish on the shared broker, other projects' included, depend on Laravel being up.

Devices get their topic base from `GET /api/v1/device/mqtt-credentials` instead of hard-coding it, so changing the prefix needs no firmware release. Device usernames stay bare UUIDs. On a shared broker they share one username space with other projects, which is acceptable because UUIDs don't collide.

This replaces the `devices/{uuid}/...` topics and file-based ACL described in ADR-0002.
