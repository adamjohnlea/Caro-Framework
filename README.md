# Caro Framework

A modern, modular PHP framework built with Domain-Driven Design principles, featuring SQLite persistence, optional modules for authentication, email, and background jobs.

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

## Documentation

- **[Starting Your Own Project](STARTINGYOUROWNPROJECT.md)** - Beginner-friendly guide with everything you need to build your first application
- **[Getting Started](GETTINGSTARTED.md)** - Quick setup guide for developers
- **[Project Instructions](CLAUDE.md)** - Technical documentation for contributors

## Features

- **Modern PHP 8.4** with strict types and readonly classes
- **Modular architecture** - enable only the features you need
- **Domain-Driven Design** with clean layer separation enforced by deptrac
- **SQLite-first** with fluent query builder
- **PSR-11 container** with self-registering module providers
- **Session-based authentication** with CSRF protection
- **Flash messages** across redirects with auto-dismiss
- **Named route URL generation** via `path()` Twig function and `UrlGenerator`
- **Event dispatcher** for decoupled cross-module communication
- **Email service** with AWS SES support and log fallback
- **Background job queue** with database-backed persistence
- **Reflection-based controller dispatch** with automatic parameter injection
- **Test-Driven Development** with PHPUnit 11.x
- **Strict quality checks** - PHPStan level 10, PHP-CS-Fixer, Rector
- **Modern frontend** with Tailwind CSS 4 and Alpine.js

## Requirements

- **PHP 8.4+** with extensions: `json`, `pdo`, `pdo_sqlite`
- **Composer** for dependency management
- **Node.js & npm** for frontend assets
- **Laravel Herd** (recommended) or any PHP development environment

## Installation

### 1. Clone and install dependencies

```bash
git clone https://github.com/adamjohnlea/Caro-Framework.git your-project-name
cd your-project-name
composer install
npm install
```

### 2. Set up Git hooks

```bash
composer hooks:install
```

This installs the pre-commit hook that enforces code quality checks.

### 3. Create database file

```bash
touch storage/database.sqlite
chmod -R 775 storage
```

The database file is git-ignored, so you need to create it after cloning.

### 4. Configure environment

```bash
cp .env.example .env
```

Edit `.env` to configure your application:

```ini
APP_ENV=local
APP_DEBUG=true
APP_NAME="My App"

DB_DRIVER=sqlite
# DB_PATH is optional - defaults to storage/database.sqlite

# Enable modules as needed
MODULE_AUTH=true
MODULE_EMAIL=false
MODULE_QUEUE=false
```

### 5. Build frontend assets

```bash
npm run build       # One-time build
npm run dev         # Watch mode for development

# Copy Alpine.js (required for interactivity)
cp node_modules/alpinejs/dist/cdn.min.js public/js/alpine.min.js
```

Migrations run automatically on the first web request.

### 6. Create an admin user (if using Auth module)

```bash
php cli/create-admin.php --email=admin@example.com --password=secret123
```

## Architecture

Caro Framework uses a modular, domain-driven architecture with strict layer boundaries:

```
src/
├── Database/              # Database wrapper, migrations, query grammar
├── Http/
│   ├── Controllers/       # Core HTTP controllers (Home, Health)
│   ├── Middleware/         # Request/response middleware
│   ├── ControllerDispatcher.php   # Reflection-based dispatch
│   ├── UrlGenerator.php           # Named route URL generation
│   ├── RouteProviderInterface.php       # Module route self-registration
│   ├── MiddlewareProviderInterface.php  # Module middleware self-registration
│   └── RouteAccessRegistry.php          # Public/admin route registry
├── Modules/
│   ├── Auth/              # Authentication module
│   │   ├── AuthServiceProvider.php  # Services, routes, middleware
│   │   └── Http/Controllers/        # Auth and User controllers
│   ├── Email/             # Email service module
│   │   └── EmailServiceProvider.php
│   ├── Queue/             # Background job queue module
│   │   └── QueueServiceProvider.php
│   └── {YourModule}/      # Custom modules
│       ├── Application/   # Use cases and services
│       ├── Domain/        # Entities, value objects, interfaces
│       ├── Infrastructure/# Repository implementations
│       ├── Http/Controllers/ # Module controllers
│       └── {Module}ServiceProvider.php  # Services, routes, middleware
├── Shared/
│   ├── Cli/               # CliBootstrap for CLI container setup
│   ├── Container/         # ContainerInterface (PSR-11) and Container
│   ├── Database/          # Fluent query builder
│   ├── Events/            # EventDispatcher for cross-module communication
│   ├── Exceptions/        # Shared exception types
│   ├── Providers/         # ServiceProvider base class
│   ├── Session/           # FlashMessageService
│   └── Twig/              # Twig extensions (AppExtension)
└── Views/                 # Twig templates
```

### Layer Rules

The architecture enforces clean dependencies using deptrac:

- **Domain Layer**: No dependencies on other layers (only Shared)
- **Application Layer**: Depends on Domain and Shared only
- **Infrastructure Layer**: Can depend on Domain, Application, Shared, Database
- **Http Layer**: Depends on Application, Domain, Shared, Database
- **Shared Layer**: Can depend on Database (QueryBuilder wraps Database)

## Modules

All modules are opt-in via `.env` configuration flags.

### Auth Module

**Enable:** `MODULE_AUTH=true`

Provides session-based authentication with role-based access control:

- Login/logout functionality with flash messages
- User CRUD operations (admin-only) with flash messages
- CSRF protection on POST/PUT/DELETE requests
- Middleware: `AuthenticationMiddleware`, `CsrfMiddleware`, `AuthorizationMiddleware`
- Self-registers routes, middleware, and route access via provider interfaces

**Create an admin user:**

```bash
php cli/create-admin.php --email=admin@example.com --password=secret123
```

### Email Module

**Enable:** `MODULE_EMAIL=true`

Flexible email service with multiple backends:

- `SesEmailService` - Production email via AWS SES v2
- `LogEmailService` - Development fallback that logs emails instead of sending

**Configuration for SES:**

```ini
MODULE_EMAIL=true
AWS_SES_REGION=us-east-1
AWS_SES_ACCESS_KEY=your-key
AWS_SES_SECRET_KEY=your-secret
AWS_SES_FROM_ADDRESS=noreply@example.com
```

**Usage:**

```php
$emailService = $container->get(EmailServiceInterface::class);

$emailService->send(
    to: 'user@example.com',
    subject: 'Welcome!',
    body: 'Thanks for signing up.'
);

$emailService->sendWithAttachment(
    to: 'user@example.com',
    subject: 'Your invoice',
    body: 'See attached.',
    attachmentPath: '/path/to/invoice.pdf',
    attachmentName: 'invoice.pdf',
    mimeType: 'application/pdf'
);
```

### Queue Module

**Enable:** `MODULE_QUEUE=true`

Database-backed job queue with worker process:

- Transaction-locked job claiming prevents double-processing
- Automatic retry with exponential backoff
- Manual retry for failed jobs
- Graceful shutdown on SIGTERM/SIGINT

**Create a job:**

```php
use App\Modules\Queue\Domain\JobInterface;
use App\Shared\Container\ContainerInterface;

readonly class SendWelcomeEmail implements JobInterface
{
    public function __construct(
        private string $email,
    ) {}

    public function handle(ContainerInterface $container): void
    {
        // Jobs receive the container interface and can access services
        $emailService = $container->get(EmailServiceInterface::class);
        $emailService->send($this->email, 'Welcome!', 'Thanks for joining.');
    }

    public function getQueue(): string
    {
        return 'default';
    }

    public function getMaxAttempts(): int
    {
        return 3;
    }
}
```

**Dispatch a job:**

```php
$queueService = $container->get(QueueService::class);
$queueService->dispatch(new SendWelcomeEmail('user@example.com'));
```

**Run the worker:**

```bash
php cli/worker.php --queue=default --sleep=3
```

The worker will process jobs from the queue continuously. Use Ctrl+C for graceful shutdown.

## Query Builder

Caro includes a fluent, immutable query builder for SQLite:

```php
use App\Shared\Database\QueryBuilder;

$users = QueryBuilder::table('users')
    ->select(['id', 'name', 'email'])
    ->where('role', '=', 'admin')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

$affectedRows = QueryBuilder::table('users')
    ->where('id', '=', 123)
    ->update(['name' => 'New Name']);

QueryBuilder::table('users')->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);
```

The query builder is dialect-aware and returns raw associative arrays. Domain repositories handle hydration to domain models.

## Development

### Test-Driven Development

**TDD is mandatory.** Write tests first, then implement. Every feature, bug fix, or refactoring must have test coverage.

```bash
composer test              # Run all tests
composer test:coverage     # Generate coverage report
```

Test structure:

- `tests/Unit/` - Fast, isolated tests (no I/O)
- `tests/Integration/` - Tests with database (in-memory SQLite)
- `tests/Feature/` - End-to-end HTTP request tests

### Code Quality

Run quality checks before every commit:

```bash
composer quality           # Run all checks: cs-check, phpstan, deptrac, test
composer cs-fix            # Auto-fix code style
composer rector:fix        # Apply automated refactorings
```

The pre-commit hook enforces quality checks automatically.

**Quality tools:**

- **PHPStan** - Level 10 + strict-rules for maximum type safety
- **PHP-CS-Fixer** - PSR-12 code style with custom rules
- **deptrac** - Enforce architectural layer boundaries
- **Rector** - Automated refactoring and modernization
- **PHPUnit 11.x** - Testing framework

### CLI Tools

```bash
# Create an admin user
php cli/create-admin.php --email=admin@example.com --password=secret123

# Run queue worker
php cli/worker.php --queue=default --sleep=3

# Health check
php cli/doctor.php
```

### Frontend Development

Tailwind CSS 4 with watch mode:

```bash
npm run dev        # Watch mode - rebuilds on changes
npm run build      # Production build
```

Templates use Twig with asset versioning and named route URLs:

```twig
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<a href="{{ path('users.index') }}">Users</a>
<a href="{{ path('users.edit', {id: user.id}) }}">Edit</a>
```

## Creating a New Module

Follow these steps to add a new module:

1. **Create directory structure:**
   ```
   src/Modules/{ModuleName}/
   ├── Application/
   │   └── Services/
   ├── Domain/
   │   ├── Models/
   │   ├── ValueObjects/
   │   └── Repositories/
   ├── Infrastructure/
   │   └── Repositories/
   ├── Database/
   │   └── Migrations/
   ├── Http/
   │   └── Controllers/
   └── {ModuleName}ServiceProvider.php
   ```

2. **Start with Domain Layer** - Define value objects, entities, and repository interfaces

3. **Add Application Layer** - Create services that orchestrate domain logic

4. **Add Infrastructure Layer** - Implement repository interfaces with SQLite (prefer QueryBuilder for simple CRUD)

5. **Create ServiceProvider** - Register services, routes, middleware, and route access. Implement provider interfaces as needed:
   ```php
   <?php
   namespace App\Modules\YourModule;

   use App\Http\RouteProviderInterface;
   use App\Http\Router;
   use App\Shared\Providers\ServiceProvider;

   final class YourModuleServiceProvider extends ServiceProvider implements RouteProviderInterface
   {
       public function register(): void
       {
           $this->container->set(YourServiceInterface::class, function () {
               // Register services
           });
       }

       public function routes(Router $router): void
       {
           $router->get('/your-route', YourController::class, 'index', 'your.index');
           $router->post('/your-route', YourController::class, 'store', 'your.store');
       }

       public function boot(): void
       {
           // Optional: Post-registration setup
       }
   }
   ```

6. **Place controllers** in `src/Modules/{ModuleName}/Http/Controllers/` with namespace `App\Modules\{ModuleName}\Http\Controllers`

7. **Add SQL migration** in `src/Modules/{ModuleName}/Database/Migrations/` using timestamp naming: `YYYY_MM_DD_HHMMSS_description.sql`

8. **Add module toggle:**
   - `.env.example`: `MODULE_{NAME}=false`
   - `config/config.php`: Add to config array
   - `MigrationRunner`: Add to module map

9. **Register provider in index.php**:
   ```php
   if ($config['modules']['yourmodule']) {
       $container->registerProvider(new YourModuleServiceProvider($container, $config));
   }
   ```
   Routes, middleware, and route access are automatically collected from the provider interfaces.

10. **Add Twig templates** in `src/Views/{module}/` — use `{{ path('route.name') }}` for all URLs

11. **Verify deptrac passes** - No layer violations allowed

## Code Conventions

### PHP

- Every file starts with `declare(strict_types=1)`
- Use `readonly class` for immutable objects
- Use `#[Override]` attribute on interface implementations
- All class references use `use` imports, never inline `\Namespace\Class`
- Value objects validate in constructor and remain immutable
- Repository interfaces in Domain, implementations in Infrastructure
- Controllers stay thin - delegate to Application Services

### Testing

- Test file mirrors source structure
- Unit tests mock external dependencies
- Integration tests use in-memory SQLite
- Feature tests exercise full request lifecycle

### Frontend

- Tailwind CSS 4 uses `@import "tailwindcss"` syntax
- Alpine.js for interactivity
- Asset URLs use `{{ asset('path') }}` for cache busting
- Avoid inline JavaScript

## License

Caro Framework is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, version 3.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.

See the [LICENSE](LICENSE) file for the complete license text.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests first (TDD)
4. Implement your feature
5. Run `composer quality` - all checks must pass
6. Submit a pull request

## Support

[Add support information here]
