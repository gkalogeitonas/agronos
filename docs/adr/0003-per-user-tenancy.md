---
status: accepted
---

# A tenant is a user, isolated by a global scope

Every farm, device and sensor row carries a `user_id`, and the `BelongsToTenant` trait adds a global scope that filters every query to the signed-in user. Policies authorise actions on top of that, as defence in depth. Code that runs without a signed-in user (device APIs, webhooks, jobs) must opt out explicitly with `allTenants()`/`withoutTenantScope()`. Introducing organisations (issue #5) would supersede this ADR.
