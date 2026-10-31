# Agronos

- Thesis-era reports, PRD and technical reference were removed after the thesis; they live in release `thesis-v1.0`. They are history, not current design: current decisions are in `docs/adr/`.
- Device firmware (ESP32, PlatformIO): https://github.com/gkalogeitonas/Agronos-iot-device
- After `boost:install` or `boost:update`, make sure `.mcp.json` and `.vscode/mcp.json` run the server as `ddev exec php artisan boost:mcp`.

## Tenancy

Farm, Device and Sensor are tenant-scoped (ADR-0003). Code that runs without a signed-in user (device API, webhooks, jobs, commands) must use `allTenants()`.
Feature tests for a tenant-scoped resource cover, per action, both the owner succeeding and another user getting 403 or not seeing the record.

## UI

Prefer [ShadCN](https://ui.shadcn.com/) Vue components for frontend UI.

## Before opening a PR

Run the same checks as `.github/workflows/lint.yml` locally and fix everything they report before pushing:
- `ddev exec vendor/bin/pint --test` (fix with `ddev exec vendor/bin/pint`)
- `ddev exec npm run format:check` (fix with `ddev exec npm run format`)
- `ddev exec npx eslint .` (fix with `--fix`)

## Device Simulator (local development)

To exercise devices without hardware, use `ddev artisan devices:simulate` (`app/Console/Commands/SimulateDevices.php`). It sends fake readings for the seeded devices `--via=direct|http|mqtt`.
- `mqtt` needs the EMQX rules from `ddev artisan mqtt:setup-webhook`.
- `http` and `mqtt` log in with the seeded device secret, so re-run `ddev artisan db:seed` if login returns 401.
- See "Simulating devices (dev)" in `readme.md` for options and prerequisites. Don't run it against production.
