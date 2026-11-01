---
status: accepted
---

# A tenant is a user, isolated by a global scope

Every farm, device and sensor row carries a `user_id`, and the `BelongsToTenant` trait adds a global scope that filters every query to the signed-in user. Policies authorise actions on top of that, as defence in depth. Code that runs without a signed-in user (device APIs, webhooks, jobs) must opt out explicitly with `allTenants()`/`withoutTenantScope()`. Introducing organisations (issue #5) would supersede this ADR.

## Validation rules bypass the scope

The global scope only applies to Eloquent queries. The `exists` and `unique` validation rules query the database directly, so they see every tenant. Scope an `exists` rule to the signed-in user, for example `Rule::exists('farms', 'id')->where('user_id', $this->user()->id)`, wherever the id comes from user input. `unique` on a UUID is the deliberate exception (see ADR-0004). Policies do not help here: they authorise the resource being acted on, not ids in the payload.
