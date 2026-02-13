# Getting Started

## Prerequisites

- **PHP 8.4** with extensions: `pdo_sqlite`, `json`
- **Composer** 2.x
- **Node.js** 18+ and npm
- **Laravel Herd** (or any local PHP server)

## Quick Setup

```bash
# 1. Clone the framework to your new project directory
git clone https://github.com/adamjohnlea/Caro-Framework.git my-new-project
cd my-new-project

# 2. Install dependencies and set up git hooks
composer setup
npm install

# 4. Create your environment file
cp .env.example .env

# 5. Create the database file (git-ignored, so not cloned)
touch storage/database.sqlite
chmod -R 775 storage

# 6. Build the CSS
npm run build

# 7. Copy Alpine.js to public directory
cp node_modules/alpinejs/dist/cdn.min.js public/js/alpine.min.js

# 8. Create your first admin user (auth module is enabled by default)
php cli/create-admin.php --email=admin@example.com --password=secret123

# 9. Verify everything works
composer quality
php cli/doctor.php
```

Open your project in the browser. You should see the welcome page with module status. Log in with your admin credentials.

## Module Configuration

Modules are toggled via `.env` flags. Edit your `.env` file:

```env
MODULE_AUTH=true     # Authentication, users, CSRF (enabled by default)
MODULE_EMAIL=false   # Email service (SES or log fallback)
MODULE_QUEUE=false   # Database-backed job queue
```

### Auth Module (`MODULE_AUTH=true`)

Provides session-based authentication with login/logout, user CRUD, and role-based access.

```bash
# Create an admin user
php cli/create-admin.php --email=admin@example.com --password=secret123

# Create a viewer user
php cli/create-admin.php --email=viewer@example.com --password=secret123 --role=viewer
```

Routes: `/login`, `/logout`, `/users`, `/users/create`, `/users/{id}/edit`

When disabled, no session is started, no auth middleware runs, and no login routes exist.

### Email Module (`MODULE_EMAIL=true`)

Provides `EmailServiceInterface` with two implementations:

- **SES mode** — Set `AWS_SES_*` env vars for production email via Amazon SES v2
- **Log mode** — Falls back to logging emails when no SES credentials are configured

```env
AWS_SES_REGION=us-east-1
AWS_SES_ACCESS_KEY=your-key
AWS_SES_SECRET_KEY=your-secret
AWS_SES_FROM_ADDRESS=noreply@example.com
```

### Queue Module (`MODULE_QUEUE=true`)

Database-backed job queue. Dispatch jobs from your application, process them with a background worker.

```bash
# Start the worker
php cli/worker.php --queue=default --sleep=3
```

Create a job by implementing `JobInterface`:

```php
use App\Shared\Container\ContainerInterface;

final readonly class SendWelcomeEmailJob implements JobInterface
{
    public function __construct(private int $userId) {}

    #[Override]
    public function handle(ContainerInterface $container): void
    {
        // Jobs receive the container interface to access services
        $emailService = $container->get(EmailServiceInterface::class);
        // Send the email...
    }

    #[Override]
    public function getQueue(): string { return 'email'; }

    #[Override]
    public function getMaxAttempts(): int { return 3; }
}
```

Dispatch it:
```php
$queueService->dispatch(new SendWelcomeEmailJob($userId));
```

## Customize for Your Project

### 1. Update project identity

**composer.json** — Change `name` and `description`:
```json
{
    "name": "my-org/my-app",
    "description": "What this project does"
}
```

**package.json** — Change `name`:
```json
{
    "name": "my-app"
}
```

**.env.example** — Set your app name:
```
APP_NAME="My App"
```

### 2. Add your first module

Modules live in `src/Modules/`. Follow this structure:

```
src/Modules/Todo/
    Application/
        Services/
            TodoService.php
    Domain/
        Models/
            Todo.php
        ValueObjects/
            TodoTitle.php
        Repositories/
            TodoRepositoryInterface.php
    Infrastructure/
        Repositories/
            SqliteTodoRepository.php
    Database/
        Migrations/
            2025_06_15_120000_create_todos_table.sql
    Http/
        Controllers/
            TodoController.php
    TodoServiceProvider.php
```

Start with the Domain layer (value objects + interfaces), write tests first, then build outward. See `CLAUDE.md` for the full workflow.

### 3. Create your first migration

Add a timestamp-named SQL file to `src/Modules/{ModuleName}/Database/Migrations/`:

```sql
-- src/Modules/Todo/Database/Migrations/2025_06_15_120000_create_todos_table.sql
CREATE TABLE IF NOT EXISTS todos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    completed INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);
```

Migration files use timestamp naming (`YYYY_MM_DD_HHMMSS_description.sql`) to avoid numbering conflicts between modules. Add your module to the `MigrationRunner` module map.

### 4. Create a ServiceProvider

Each module registers its own services, routes, and middleware through a ServiceProvider:

```php
<?php
namespace App\Modules\Todo;

use App\Http\RouteProviderInterface;
use App\Http\Router;
use App\Shared\Providers\ServiceProvider;

final class TodoServiceProvider extends ServiceProvider implements RouteProviderInterface
{
    public function register(): void
    {
        $this->container->set(TodoRepositoryInterface::class, function () {
            $database = $this->container->get(Database::class);
            return new SqliteTodoRepository($database);
        });
    }

    public function routes(Router $router): void
    {
        $router->get('/todos', TodoController::class, 'index', 'todos.index');
        $router->post('/todos', TodoController::class, 'store', 'todos.store');
    }
}
```

Then register it in `public/index.php` behind a module flag:

```php
if ($config['modules']['todo']) {
    $container->registerProvider(new TodoServiceProvider($container, $config));
}
```

Routes and middleware are automatically collected from the provider interfaces.

### 5. Add templates

Create Twig templates in `src/Views/{module}/` extending the base layout:

```twig
{% extends "layouts/base.twig" %}

{% block title %}Todos - {{ appName }}{% endblock %}

{% block page_header %}
    <h1 class="text-sm font-semibold text-text-primary">Todos</h1>
{% endblock %}

{% block content %}
    <a href="{{ path('todos.store') }}">Add Todo</a>
    {# Your content here #}
{% endblock %}
```

Use `{{ path('route.name') }}` for all URLs instead of hardcoding paths. Navigation in `base.twig` uses path() and is driven by module configuration.

## CLI Tools

```bash
php cli/create-admin.php    # Create admin/viewer users
php cli/worker.php          # Run the queue worker
php cli/doctor.php          # Health check all systems
```

### Doctor output

```
Caro Framework Health Check
===========================
[OK] Database: Connected (Sqlite)
[OK] Migrations: 2 applied
[OK] Auth: Enabled, 1 user(s)
[--] Email: Disabled
[--] Queue: Disabled
```

## Quality Toolchain

Every tool is configured and ready to use:

| Command | What it does |
|---|---|
| `composer quality` | Runs cs-check, phpstan, deptrac, test in sequence |
| `composer cs-check` | Check code style (PSR-12 + PHP 8.4) |
| `composer cs-fix` | Auto-fix code style violations |
| `composer phpstan` | Static analysis at level 10 with strict rules |
| `composer deptrac` | Verify architectural layer boundaries |
| `composer test` | Run PHPUnit test suites |
| `composer rector` | Preview automated refactorings (dry run) |
| `composer rector:fix` | Apply automated refactorings |
| `npm run build` | Build Tailwind CSS (minified) |
| `npm run dev` | Watch mode for Tailwind CSS |

### Pre-commit hook

The git pre-commit hook runs `composer quality` automatically. It was installed by `composer setup`. If you need to reinstall it:

```bash
composer hooks:install
```

### CI/CD

GitHub Actions is configured in `.github/workflows/ci.yml`. It runs the full quality suite on every push to `main` and on pull requests.

## Architecture at a Glance

```
Request → Middleware Pipeline → Router → Controller → Service → Domain
                                                         ↓
                                              Repository Interface
                                                         ↓
                                              SQLite Implementation
                                                         ↓
                                                    Response ← Twig Template
```

### Layer rules (enforced by deptrac)

- **Domain** depends on nothing (only Shared)
- **Application** depends on Domain
- **Infrastructure** implements Domain interfaces
- **Http** calls Application services (and Database for infrastructure endpoints)
- **Shared** is available everywhere (can use Database for QueryBuilder)

### Middleware pipeline

Middleware wraps the entire request/response cycle. The following middleware is included:

| Middleware | Module | Description |
|---|---|---|
| `SecurityHeadersMiddleware` | Core | CSP, X-Frame-Options, etc. |
| `AuthenticationMiddleware` | Auth | Redirects unauthenticated users to `/login` |
| `CsrfMiddleware` | Auth | Validates CSRF token on POST requests |
| `AuthorizationMiddleware` | Auth | Blocks non-admin users from `/users` routes |

### Error handling

- Unhandled exceptions are caught, logged to `storage/logs/app.log`, and render `errors/500.twig`
- In debug mode (`APP_DEBUG=true`), the stack trace is shown on the error page
- In production, a clean error page is shown with no sensitive information
- 404 errors render `errors/404.twig`

### Asset versioning

Use the `{{ asset('path') }}` Twig function instead of hardcoded paths:

```twig
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
```

This appends `?v={timestamp}` to bust browser caches on deploy.

### Health check

`GET /health` returns JSON with the status of system dependencies:

```json
{
    "status": "healthy",
    "checks": {
        "database": { "ok": true, "message": "Connected" }
    }
}
```

Use this endpoint for load balancer health checks and uptime monitoring.

## Working with Claude Code

This scaffold includes a `CLAUDE.md` file with instructions that Claude Code reads automatically. It tells Claude about:

- The architecture and layer rules
- TDD workflow requirements
- Code conventions and gotchas
- Module documentation (Auth, Email, Queue, Query Builder)
- How to add new modules

When starting a new feature, tell Claude what module you're building and it will follow the established patterns.
