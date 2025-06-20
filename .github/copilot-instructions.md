# Copilot Custom Instructions: Documentation Files

## Documentation Location

All project documentation is located in the `docs/` directory at the root of the repository.

### Files and Contents

- **docs/Laravel_guidelines.md**  
  Contains best practices and conventions for Laravel development in this project.  
  Includes:  
  - Resource class structure (Models, Controllers, API Resources, Form Requests, Policies, Factories, Seeders, Migrations)
  - Testing strategy (using Pest PHP)
  - Multi-tenancy implementation using global scopes and traits

- **docs/prd.md**  
  Product Requirements Document (PRD) for the Smart Agriculture IoT Platform.  
  Includes:  
  - Project overview and goals
  - Target users and user stories
  - Technology stack
  - Key features and deliverables
  - Security considerations
  - Database entities and data strategy

- **docs/technical_reference.md**  
  Technical reference for the Agronos project.  
  Includes:  
  - Project evolution and architecture
  - Data models and relationships (with tables)
  - Database schema (MySQL/PostgreSQL and InfluxDB)
  - Device HTTP endpoints and payloads
  - MQTT integration details
  - Frontend implementation notes
  - Future enhancements

## Usage for Copilot

When generating code or answering questions, please consider the guidelines and information provided in these documentation files.  
- For Laravel code, follow the conventions in `docs/Laravel_guidelines.md`.
- For project requirements and features, refer to `docs/prd.md`.
- For technical architecture, data models, and API endpoints, use `docs/technical_reference.md`.

Refer to these files for authoritative information about project requirements, conventions, and technical details.

---

## Laravel Artisan Command Guidance

**When you need to create a new class (such as a Model, Controller, Migration, Seeder, Factory, Policy, or Form Request), always use the appropriate Laravel Artisan command to generate the file.**  
Prefer using Artisan commands over manual file creation to ensure consistency with Laravel standards and project conventions.

**Example:**  
To create a new model, use:  
```bash
php artisan make:model ModelName
```

Refer to the [Laravel documentation](https://laravel.com/docs/artisan) or `docs/Laravel_guidelines.md` for more details on available Artisan commands and their usage.
