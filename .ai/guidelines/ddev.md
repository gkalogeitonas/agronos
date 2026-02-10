DDEV development environment guidelines
------------------------------------

- Always run Artisan commands through DDEV in this repository. Use `ddev artisan` instead of `php artisan` to ensure the project's containerized environment, PHP version, and services are used.
- When running composer commands inside the project container prefer `ddev composer`.
- When automating tasks for the repository (scripts, CI, or LLM-run commands), prefer DDEV-prefixed commands so environment-specific PHP, MySQL, and other services match local development.

Examples

- Run tests: `ddev artisan test`
- Run migrations: `ddev artisan migrate`
- Install Boost: `ddev composer require --dev laravel/boost` then `ddev artisan boost:install`

Notes for the LLM agent

- When executing or suggesting commands that interact with Laravel, prefer the `ddev` wrapper. If the user indicates they are not using DDEV, fall back to `php artisan`/`composer` and confirm.
- Use `ddev` for commands that require the project's PHP binary, database, or other containerized services.
