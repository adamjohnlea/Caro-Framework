# Project Instructions for Claude Code

## Architecture

This is a PHP 8.4 + SQLite application using a modular DDD-inspired architecture.

### Tech Stack
- **Backend:** PHP 8.4, SQLite, Twig 3.x, Symfony HTTP Foundation + Routing, AWS SDK
- **Frontend:** Tailwind CSS 4, Alpine.js
- **Testing:** PHPUnit 11.x, PHPStan level 10 + strict-rules, PHP-CS-Fixer, deptrac, Rector
- **Dev environment:** Laravel Herd

### Directory Structure
```
src/
  Database/           # Database wrapper, migration runner, SQL migrations, Grammar
  Http/
    Controllers/      # HTTP controllers (thin, delegate to services)
    Middleware/        # Request/response middleware pipeline
  Modules/
    Auth/             # Authentication, users, CSRF, session management
    Email/            # Email service (SES + Log fallback)
    Queue/            # Database-backed job queue with worker
    {ModuleName}/
      Application/
        Services/     # Use cases, orchestration logic
      Domain/
        Models/       # Entities, aggregates
        ValueObjects/ # Immutable value types with validation
        Repositories/ # Interface definitions only
      Infrastructure/
        Repositories/ # SQLite implementations of domain interfaces
      Database/
        Migrations/   # Module-specific SQL migrations
  Shared/
    Container/        # Simple service container
    Database/         # QueryBuilder (fluent, immutable)
    Exceptions/       # Shared exception types
    Twig/             # Twig extensions
  Views/
    layouts/          # Base Twig templates
    errors/           # Error page templates
    auth/             # Login page
    users/            # User management CRUD
    css/              # Tailwind source CSS
cli/
  create-admin.php    # Create admin user
  worker.php          # Queue worker process
  doctor.php          # Health check / smoke test
tests/
  Unit/               # Fast, isolated tests (no I/O)
  Integration/        # Tests with database (in-memory SQLite)
  Feature/            # End-to-end request tests
```

### Layer Rules (enforced by deptrac)
- **Domain** has NO dependencies on other layers (only Shared)
- **Application** depends on Domain and Shared only
- **Infrastructure** can depend on Domain, Application, Shared, Database
- **Http** depends on Application, Domain, Shared, Database
- **Shared** can depend on Database (QueryBuilder wraps Database)

## Modules

All modules are opt-in via `.env` config flags.

### Auth Module (`MODULE_AUTH=true`)
- Session-based authentication with login/logout
- User CRUD (admin-only) with role-based access
- CSRF protection on POST requests
- Middleware: `AuthenticationMiddleware`, `CsrfMiddleware`, `AuthorizationMiddleware`
- CLI: `php cli/create-admin.php --email=admin@example.com --password=secret123`

### Email Module (`MODULE_EMAIL=true`)
- `EmailServiceInterface` with `send()` and `sendWithAttachment()` methods
- `SesEmailService` for production (AWS SES v2)
- `LogEmailService` fallback when no SES credentials configured
- Requires `AWS_SES_*` env vars for SES mode

### Queue Module (`MODULE_QUEUE=true`)
- Database-backed job queue with `QueueService.dispatch()`, `processNext()`, `retryFailed()`
- `JobInterface` contract for queueable jobs
- Transaction-locked job claiming to prevent double-processing
- CLI worker: `php cli/worker.php --queue=default --sleep=3`
- Graceful shutdown on SIGTERM/SIGINT

### Query Builder
- `App\Shared\Database\QueryBuilder` — fluent, immutable query builder
- Dialect-aware via `GrammarInterface` (SQLite grammar included)
- Supports SELECT, INSERT, INSERT IGNORE, UPDATE, DELETE, JOIN, ORDER BY, LIMIT
- Returns raw assoc arrays — hydration stays in repositories
- Available as an alternative to raw SQL via `Database::query()`

## Development Workflow

### TDD is mandatory
Write tests first. Red-Green-Refactor. Every new feature, bug fix, or refactoring must have test coverage.

### Quality checks
```bash
composer quality    # Runs: cs-check, phpstan, deptrac, test
composer cs-fix     # Auto-fix code style
composer rector:fix # Auto-apply Rector refactorings
```

**ALWAYS run `composer quality` before every commit.** No exceptions, even for template-only changes.

The pre-commit hook enforces this, but run it manually to catch issues early.

### Building CSS
```bash
npm run build  # One-time build
npm run dev    # Watch mode
```

### CLI Tools
```bash
php cli/create-admin.php --email=admin@example.com --password=secret123
php cli/worker.php --queue=default --sleep=3
php cli/doctor.php
```

## Code Conventions

### PHP
- Every file starts with `declare(strict_types=1)`
- Use `readonly class` where all properties are immutable
- Use `#[Override]` on interface implementations
- All class references use `use` imports, not inline `\Foo`
- PHP-CS-Fixer prefers `{ }` on a new line for empty constructors (not `{}`)
- `PDO::lastInsertId()` returns `string|false` — PHPStan catches this at level 10
- Value objects validate in their constructor and are immutable
- Repository interfaces live in Domain, implementations in Infrastructure
- Controllers are thin — they delegate to Application Services
- Avoid `new ClassName()->method()` chaining — deptrac's parser can't handle it. Use a variable.

### Frontend
- Tailwind CSS 4 uses `@import "tailwindcss"` (not `@tailwind` directives)
- Asset paths use the `{{ asset('path') }}` Twig function for cache busting
- Alpine.js for interactive behavior, avoid inline JS

### Testing
- Unit tests: no database, no filesystem, mock external dependencies
- Integration tests: use in-memory SQLite (`:memory:` via phpunit.xml env)
- Feature tests: full request lifecycle
- Test file mirrors source: `src/Modules/Foo/Domain/Bar.php` → `tests/Unit/Modules/Foo/BarTest.php`
- Base `Tests\TestCase` provides in-memory DB + `runMigrations()` for integration tests

## Adding a New Module

1. Create the directory structure under `src/Modules/{ModuleName}/`
2. Start with Domain layer (value objects, models, repository interfaces)
3. Add Application layer (services)
4. Add Infrastructure layer (SQLite repositories)
5. Add SQL migration in `src/Modules/{ModuleName}/Database/Migrations/` (globally numbered)
6. Add module toggle to `.env.example`, `config/config.php`, and `MigrationRunner` module map
7. Register services in the container (`public/index.php`) behind the module flag
8. Add routes and controller
9. Add Twig templates under `src/Views/{module}/`
10. Ensure deptrac passes — no layer violations
