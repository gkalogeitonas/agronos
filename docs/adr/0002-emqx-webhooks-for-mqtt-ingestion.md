---
status: accepted
---

# MQTT reaches Laravel through EMQX webhooks, not a subscriber service

The broker is EMQX. Instead of running a separate Node or Go service that subscribes to every topic (the original plan), EMQX's rule engine POSTs each message to a Laravel webhook. Laravel then handles it through the same ingestion path as an HTTP reading, so there is one ingestion pipeline and no extra service to deploy. EMQX also holds per-device credentials and ACLs that confine each device to its own `devices/{uuid}/...` topics. (Topics are now namespaced and the ACL is per-user; see ADR-0007.)
