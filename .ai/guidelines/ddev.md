DDEV development environment guidelines
------------------------------------

- Always run Artisan commands through DDEV in this repository. Use `ddev artisan` instead of `php artisan` to ensure the project's containerized environment, PHP version, and services are used.
- When running composer commands inside the project container prefer `ddev composer`.
- When automating tasks for the repository (scripts, CI, or LLM-run commands), prefer DDEV-prefixed commands so environment-specific PHP, MySQL, and other services match local development.

Examples

- Run tests: `ddev artisan test`
- Run migrations: `ddev artisan migrate`
- Install Boost: `ddev composer require --dev laravel/boost` then `ddev artisan boost:install`

Browser tests

- Run the Pest browser tests (`tests/Browser`) with `ddev browser-tests`. It runs `npm run build` and then `php artisan test tests/Browser` inside the container. It passes Pest arguments through, for example `ddev browser-tests --filter=toast`.
- Browser tests always load the compiled assets in `public/build` and ignore `public/hot`, so they pass even while `ddev composer run dev` is running. If you use `ddev artisan test tests/Browser` directly, run `ddev npm run build` first. Otherwise the tests check a stale build.
- Run the dev server through DDEV (`ddev composer run dev`), not with the host's `php`/`composer`.

Notes for the LLM agent

- When executing or suggesting commands that interact with Laravel, prefer the `ddev` wrapper. If the user indicates they are not using DDEV, fall back to `php artisan`/`composer` and confirm.
- Use `ddev` for commands that require the project's PHP binary, database, or other containerized services.
