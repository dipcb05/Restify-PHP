Restify-PHP Documentation
=========================

**Restify-PHP** is a native PHP 8+ micro-framework focused on simplicity, performance, and zero dependencies. Drop it into any environment, point the server at `public/`, and build APIs without Composer, scaffolding, or heavy bootstrapping.


---

Table of Contents
-----------------

1. [Introduction](#introduction)
2. [Quick Start](#quick-start)
3. [Project Structure](#project-structure)
4. [Runtime Architecture](#runtime-architecture)
5. [Routing](#routing)
    - [Route Files](#route-files)
    - [API Directory](#api-directory)
    - [Class-Based Routes](#class-based-routes)
    - [Route Parameters & Return Values](#route-parameters--return-values)
6. [Responses](#responses)
7. [Middleware](#middleware)
8. [Async Runtime](#async-runtime)
9. [Database Connectivity](#database-connectivity)
10. [Caching & Opcode Support](#caching--opcode-support)
11. [Logging & Observability](#logging--observability)
12. [Authentication](#authentication)
13. [Command-Line Interface](#command-line-interface)
14. [Testing](#testing)
15. [Example Application](#example-application)
16. [Packages & Contributions](#packages--contributions)
17. [Hosting & Deployment](#hosting--deployment)
18. [Security Checklist](#security-checklist)
19. [Appendix](#appendix)
    - [Environment Variables](#environment-variables)
    - [Changelog](#changelog)
    - [Contributing](#contributing)
    - [License](#license)

---

Introduction
------------

Restify-PHP embraces three core principles:

- **Simplicity** – obvious structure, zero scaffolding, no hidden containers.
- **Performance** – eager autoloading, minimal abstractions, Fiber-ready async.
- **Portability** – no Composer, no PECL packages, works anywhere PHP 8+ and curl are available.

Features at a glance:

- Pure PHP 8+ with strict typing, attributes, enums, and Fibers.
- Automatic eager-loading of every class in `class/` for instant availability.
- File-based endpoints in `api/` plus attribute-driven routes with metadata.
- Unified JSON responses, validation errors, and structured exception output.
- APCu/OPcache-aware cache helpers, rate limiting, and request throttling.
- Optional Fiber-based async engine for HTTP concurrency, sockets, and background tasks.
- Turn-key OpenAPI generator (JSON/YAML) with sample payloads and Swagger UI.
- Configurable middleware stack: CORS, logging with redaction, JWT auth, error handler.
- Schema registry and JSON Schema validation for query, headers, cookies, and bodies.
- CLI for scaffolding, async runtime, OpenAPI generation, authentication, logging, and tests.
- Drop-in package system (`restify/packages`) and Docker/docker-compose templates.

---

Quick Start
-----------

### Requirements

- PHP 8.1+ recommended (Fibers require >= 8.1; 8.0 works in sync mode).
- curl extension enabled (default on most platforms).

### First Run

```bash
php -S localhost:8000 -t public
```

Visit `http://localhost:8000` to see the default JSON welcome payload.
`GET /health` returns a deployment-friendly heartbeat (`{"status":"ok"}`).

### CLI Shortcuts

```bash
php restify-cli --help
php restify-cli run --host 0.0.0.0 --port 8080 --async
php restify-cli make:class App\Domain\UserService
php restify/tests/run.php
php restify-cli docs:openapi --format yaml --serve --port 8081
php restify-cli test --phpunit
```

### Install via Composer

```bash
composer require restify-php/restify-php
php vendor/bin/restify install
php restify-cli run --async
```
---

Project Structure
-----------------

```
restify/        # Framework internals (bootstrap, config, routes, src, support, packages, tests)
    bootstrap/  # Application bootstrapper
    config/     # Framework configuration (middleware etc.)
    routes/     # Route declarations (web.php, api.php, attributes)
    src/        # Restify core
    support/    # Framework utilities (Async facade, DB helper, ClassLoader, Env)
    packages/   # Optional drop-in packages (autoloaded automatically)
    tests/      # Lightweight test runner & specs
api/            # File-based API endpoints (auto-registered)
class/          # Your domain code (autoloaded & globally available)
docs/           # Documentation, generated OpenAPI specs, Swagger UI
public/         # Web document root (contains index.php & .htaccess)
storage/        # Logs, cache, temp directories
restify-cli     # Optional CLI entry script
```

- Place reusable logic inside `class/`. Restify loads every `.php` file in this directory at startup.
- Keep only front-controller assets inside `public/` (`index.php`, `.htaccess`, static files).
- Treat everything under `restify/` as framework internals—modify with care.
- Packages dropped into `restify/packages` are automatically autoloaded (see [Packages & Contributions](#packages--contributions)).

---

Runtime Architecture
--------------------

1. `public/index.php` boots the application via `restify/bootstrap/app.php`.
2. `restify/bootstrap/app.php` defines `RESTIFY_BASE_PATH` (framework) and `RESTIFY_ROOT_PATH` (project), registers the custom autoloader, loads `.env`, and returns `Restify\Core\Application`.
3. `Application`:
   - Loads global middleware from `restify/config/middleware.php`.
   - Registers routes from `restify/routes/web.php`, `restify/routes/api.php`, route attributes inside `class/`, and endpoints declared under `api/`.
   - Bootstraps package `bootstrap.php` files located under `restify/packages/*`.
4. Requests are wrapped in immutable `Restify\Http\Request` objects and flow through the `MiddlewarePipeline`.
5. The `Router` matches paths and normalises handler output into `Restify\Http\Response`.

---

Routing
-------

### Route Files

Restify automatically loads two route files if present:

- `restify/routes/web.php` – default web endpoints.
- `restify/routes/api.php` – API-specific routes (commonly prefixed with `/api`).

Each file should return a closure receiving the shared `Router` instance.

```php
<?php

use Restify\Http\Request;
use Restify\Routing\Router;

return static function (Router $router): void {
    $router->get('/', static fn () => ['message' => 'Hello from Restify']);

    $router->get('/api/health', static fn () => ['status' => 'ok']);

    $router->post('/api/users', static function (Request $request) {
        return ['data' => $request->body];
    });
};
```

### API Directory

Drop PHP files into `api/` for switch-like endpoints without touching the router. Restify derives the path from the file name, injects an `$api` helper, and registers method handlers automatically.

```php
<?php

use Restify\Http\Request;

$api->get(static fn () => ['message' => 'List posts']);

$api->post(static fn (Request $request) => [
    'created' => $request->body,
]);

$api->path('/api/posts/{id}')
    ->get(static fn (Request $request, int $id) => ['post' => $id])
    ->delete(static fn (int $id) => ['deleted' => $id]);
```

Alternatively, return a configuration array:

```php
<?php

return [
    'PATH' => '/api/ping',
    'GET' => static fn () => ['pong' => true],
    'POST' => static fn () => ['pong' => 'created'],
    'FALLBACK' => static fn () => Restify\Http\Response::json([], 405, message: 'Try GET or POST.'),
];
```

- File path determines the default URI (`api/users.php` → `/api/users`, nested `api/admin/index.php` → `/api/admin`).
- Keys `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, and `ANY` map HTTP verbs to handlers.
- Use `PATH` to override the generated URI and `FALLBACK` to customise unsupported method responses.

### Class-Based Routes

Decorate public methods in `class/` with the `#[Route]` attribute for attribute-driven routing.

```php
<?php

declare(strict_types=1);

namespace App\Blog;

use Restify\Http\Request;
use Restify\Routing\Attributes\Route;

final class PostController
{
    #[Route('GET', '/api/posts')]
    public function index(): array
    {
        return ['posts' => $this->all()];
    }

    #[Route(['GET', 'HEAD'], '/api/posts/{id}')]
    public function show(Request $request, int $id): array
    {
        return ['post' => $this->find($id)];
    }

    #[Route('POST', '/api/posts')]
    public static function store(Request $request): array
    {
        return ['created' => $request->body];
    }
}
```

- Methods can be `public` instance methods (constructor without required arguments) or `public static`.
- Route parameters automatically map to argument names, with scalar type coercion where possible.

### Route Parameters & Return Values

Handlers may return:

- `Restify\Http\Response` for granular control.
- Arrays (auto-wrapped into JSON).
- Strings (served as `text/plain`).

Default JSON envelope:

```json
{
  "ok": true,
  "status": 200,
  "message": null,
  "data": { },
  "meta": { }
}
```

### Route Metadata & Validation

Both route files and attributes accept an optional metadata array that feeds the middleware pipeline, OpenAPI generator, and JSON Schema validator.

```php
// restify/routes/api.php
$router->post('/api/users', static fn (Request $request) => create_user($request), [
    'summary' => 'Create a user',
    'tags' => ['Users'],
    'request' => [
        'schema' => 'CreateUser',      // references config/schemas.php
        'source' => 'body',
    ],
    'responses' => [
        '201' => [
            'description' => 'User created',
            'schema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
        ],
        '422' => ['description' => 'Validation failed'],
    ],
]);
```

For attributes, pass metadata using named parameters:

```php
#[Route(
    methods: 'POST',
    path: '/api/posts',
    summary: 'Publish a post',
    request: ['schema' => 'PostPayload'],
    responses: [
        '201' => ['description' => 'Post stored'],
        '422' => ['description' => 'Validation errors'],
    ]
)]
public function store(Request $request): array
{
    // ...
}
```

- Schemas are defined in `restify/config/schemas.php` using standard JSON Schema fragments.
- `source` controls validation target: `body` (default), `query`, `headers`, or `cookies`.
- Invalid requests short-circuit with a `422` JSON payload listing validation errors.
- OpenAPI documents automatically reuse schemas and examples supplied here.

---

Responses
---------

```php
return Restify\Http\Response::json(
    ['user' => $user],
    status: 201,
    meta: ['request_id' => $request->headers['X-Request-Id'] ?? null],
    message: 'User created.'
);

return Restify\Http\Response::text('Plain content', status: 204);
```

---

Middleware
----------

Global middleware is configured in `restify/config/middleware.php`. Restify ships with a production-ready stack that covers observability, safety, and developer experience:

| Middleware                              | Purpose                                                                                 | Config source                 |
|-----------------------------------------|-----------------------------------------------------------------------------------------|-------------------------------|
| `Restify\Middleware\ExceptionMiddleware` | Converts uncaught exceptions into JSON envelopes, optional stack traces, logs incidents | `restify/config/exceptions.php` |
| `Restify\Middleware\CorsMiddleware`      | Applies CORS headers, preflight caching, credential rules                               | `restify/config/cors.php`       |
| `Restify\Middleware\RateLimitMiddleware` | APCu-backed rate limiting with per-IP buckets                                           | `.env` (`RATE_LIMIT_*`)         |
| `Restify\Middleware\AuthenticationMiddleware` | Header-driven token/JWT enforcement, per-endpoint secrets                                | `restify/config/auth.php`       |
| `Restify\Middleware\LoggingMiddleware`   | Structured request/response logging to file and database with redaction + duration      | `restify/config/logging.php`    |

`restify/config/middleware.php` wires each middleware with constructor parameters:

```php
<?php

use Restify\Middleware\AuthenticationMiddleware;
use Restify\Middleware\CorsMiddleware;
use Restify\Middleware\ExceptionMiddleware;
use Restify\Middleware\LoggingMiddleware;
use Restify\Middleware\RateLimitMiddleware;
use Restify\Support\Config;

$exceptions = Config::get('exceptions', []);
$cors = Config::get('cors', []);
$auth = Config::get('auth', []);
$logging = Config::get('logging', []);

return [
    'global' => [
        [ExceptionMiddleware::class, [$exceptions]],
        [CorsMiddleware::class, [$cors]],
        [RateLimitMiddleware::class, [
            'limit' => (int) ($_ENV['RATE_LIMIT_MAX'] ?? 60),
            'seconds' => (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
        ]],
        [AuthenticationMiddleware::class, [$auth]],
        [LoggingMiddleware::class, [$logging]],
    ],
];
```

- Middleware classes implement `Restify\Middleware\MiddlewareInterface`.
- Array entries support constructor arguments via `[ClassName::class, [arg1, arg2]]`.
- Order matters: exceptions wrap everything, logging executes last to capture responses.

Add custom middleware by pushing to the `global` array or by building route-specific pipelines before dispatching.

---

Async Runtime
-------------

`Restify\Support\Async` wraps the Fiber-based event loop (`Restify\Core\Async`) and exposes helpers:

- `Async::run(callable $callback)`
- `Async::parallel(array $callbacks)`
- `Async::http(string|array $request, array $options = [])`
- `Async::socket(string $host, int $port, string $payload = '', float $timeout = 5.0)`
- `Async::background(string $script, array $arguments = [])`
- `Async::json(array $data, int $status = 200, array $meta = [], ?string $message = null)`

Enable async mode via the CLI:

```bash
php restify-cli run --async
```

When Fibers or coroutine extensions are unavailable, Restify logs a warning and continues synchronously.

---

Database Connectivity
---------------------

`Restify\Support\DB` centralises access to popular databases (MySQL/MariaDB, PostgreSQL, SQL Server, Oracle, SQLite, MongoDB). Connections are cached and obtained directly from environment variables—no Composer packages required.

### Supported drivers

- `mysql` / `mariadb`
- `pgsql` / `postgres` / `postgresql`
- `sqlsrv` / `mssql`
- `oci` / `oracle`
- `sqlite`
- `mongodb` / `mongo`

### Environment variables

The default connection is controlled via `DB_CONNECTION` (default `mysql`). Driver-specific overrides are available; fallback keys (`DB_HOST`, `DB_PORT`, etc.) are shared.

| Driver      | Required keys                                                                                |
|-------------|-----------------------------------------------------------------------------------------------|
| mysql       | `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`     |
| pgsql       | `DB_CONNECTION=pgsql`, `DB_PGSQL_HOST`, `DB_PGSQL_PORT`, `DB_PGSQL_DATABASE`, etc.           |
| sqlsrv      | `DB_CONNECTION=sqlsrv`, `DB_SQLSRV_HOST`, `DB_SQLSRV_PORT`, `DB_SQLSRV_DATABASE`, etc.       |
| oracle      | `DB_CONNECTION=oracle`, `DB_ORACLE_HOST`, `DB_ORACLE_PORT`, `DB_ORACLE_SERVICE`, etc.        |
| sqlite      | `DB_CONNECTION=sqlite`, `DB_SQLITE_PATH` (or `DB_DATABASE`)                                  |
| mongodb     | `DB_CONNECTION=mongodb`, or provide `DB_MONGO_URI`/`DB_MONGO_HOST`/`DB_MONGO_PORT`, etc.     |

Example `.env` snippet:

```
DB_CONNECTION=pgsql
DB_PGSQL_HOST=localhost
DB_PGSQL_PORT=5432
DB_PGSQL_DATABASE=restify
DB_PGSQL_USERNAME=restify
DB_PGSQL_PASSWORD=secret
```

### Usage

```php
use Restify\Support\DB;

$pdo = DB::connection();            // Uses DB_CONNECTION or defaults to mysql
$pg  = DB::connection('pgsql');     // Explicit connection

$rows = $pdo->query('SELECT NOW() AS current_time')->fetchAll();
```

For MongoDB:

```php
$mongo = DB::connection('mongodb'); // Returns MongoDB\Client (requires mongodb extension)
```

Connections are cached; call `DB::disconnect('pgsql')` or `DB::disconnect()` to reset.

---

Caching & Opcode Support
-----------------------

`Restify\\Support\\Cache` wraps APCu and OPcache for high-speed caching and opcode priming. If either extension is missing, these helpers silently fall back to no-ops (your code keeps working).

- `Cache::put($key, $value, $seconds)` / `Cache::get($key)` / `Cache::delete($key)` / `Cache::clear()`
- `Cache::remember($key, fn () => expensive(), $ttl)` for memoised results.
- `Cache::primeOpcode($directory)` precompiles PHP files; `Cache::flushOpcode()` resets OPcache.
- Rate limiting uses `Cache::rateLimit($key, $limit, $window)` and is wired into the default middleware.

Enable APCu (set `apc.enabled=1` and `apc.enable_cli=1` for CLI) plus Zend OPcache (`opcache.enable=1`, `opcache.enable_cli=1`) in `php.ini` for best performance.

Configured via environment variables:

```
RATE_LIMIT_MAX=60
RATE_LIMIT_WINDOW=60
```

Set `RATE_LIMIT_MAX=0` to disable throttling entirely.


---

Logging & Observability
-----------------------

Restify combines structured logging, database auditing, rate limiting, and OpenAPI metadata to deliver full-stack observability.

### Request/response logging

`Restify\Middleware\LoggingMiddleware` captures every request:

- Writes JSON lines to `storage/logs/restify.log` (path configurable via `LOG_PATH`).
- Persists enriched payloads in `restify_logs` when a PDO connection exists.
- Redacts sensitive keys (defaults: `password`, `token`, `secret`, `authorization`).
- Records request/response headers (opt-in), bodies (size-limited), duration, IP, user agent.
- Downgrades to quiet mode if logging is disabled.

Configure behaviour in `restify/config/logging.php` or via `.env`:

```
LOGGING_ENABLED=true
LOG_LEVEL=info
LOG_REQUEST_BODY=true
LOG_RESPONSE_BODY=false
LOG_BODY_LIMIT=2048
LOG_DATABASE_ENABLED=true
```

Create the supporting tables once:

```bash
php restify-cli log
```

### Rate limiting

`Restify\Middleware\RateLimitMiddleware` throttles requests per IP using APCu:

```
RATE_LIMIT_MAX=120
RATE_LIMIT_WINDOW=60
```

When APCu is missing, the middleware gracefully allows all traffic (log a warning in development).

### CORS & diagnostics

`restify/config/cors.php` drives `CorsMiddleware` (origins, headers, credentials). Preflights are short-circuited with a 204 response and proper caching headers.

`GET /health` offers a JSON uptime snapshot for load balancers and monitors.

---

Authentication
--------------

The bundled authentication workflow issues tokens tied to specific endpoints and enforces them through middleware.

### Issuing a token

1. Configure your database connection and run `php restify-cli log` once (this ensures the shared tables exist).
2. Execute `php restify-cli authentication`.
3. Choose an algorithm (`md5`, `sha1`, or `jwt`), provide the target endpoint, and select the authentication scheme (`basic` or `bearer`).
4. Restify stores the token in `restify_tokens` and prints it to the console (JWTs include the generated secret).

### Using tokens

- Include the token in the `Authorization` header (`Basic <token>` or `Bearer <token>`).
- `Restify\Middleware\AuthenticationMiddleware` validates incoming requests against stored tokens. Endpoints without tokens remain public.
- JWT tokens are signed with HS256 using the stored secret, ensuring tamper protection.
- Allow-list public routes via `AUTH_PUBLIC_PATHS=/health,/docs` and customise the inspected header with `AUTH_HEADER=X-Api-Key`.
- Disable enforcement altogether with `AUTH_ENABLED=false` (useful for local development).

---

Command-Line Interface
----------------------

Use `php restify-cli` to interact with your project:

| Command            | Description                                                    | Usage Example                                              |
|--------------------|----------------------------------------------------------------|------------------------------------------------------------|
| `install`          | Publish the Restify skeleton into your project.                | `php restify-cli install`                                  |
| `run`              | Serve the application with optional async runtime.             | `php restify-cli run --host 0.0.0.0 --port 9000 --async`    |
| `make:class`       | Generate a class stub inside `class/`.                         | `php restify-cli make:class App\\Services\\Billing`        |
| `log`              | Initialise logging and authentication tables.                  | `php restify-cli log`                                      |
| `authentication`   | Issue authentication tokens tied to specific endpoints.        | `php restify-cli authentication`                           |
| `docs:openapi`     | Generate OpenAPI docs (JSON/YAML) and optional Swagger UI.     | `php restify-cli docs:openapi --format yaml --serve`       |
| `test`             | Run the PHPUnit suite or built-in runner with passthrough flags.| `php restify-cli test --phpunit --filter=ExampleTest`     |

Helpful flags:

- `php restify-cli --help`
- `php restify-cli help run`
- `php restify-cli help log`
- `php restify-cli help authentication`
- `php restify-cli docs:openapi --help`
- `php restify-cli help test`

The `install` command copies the skeleton (`restify/`, `api/`, `class/`, etc.) into your project. Composer users get this automatically via the supplied post-install script, but you can re-run it any time: `php restify-cli install` (or `php vendor/bin/restify install`).

---

Testing
-------

Restify provides two ways to execute tests:

- `php restify-cli test` auto-detects `vendor/bin/phpunit` and falls back to the native runner.
- `php restify/tests/run.php` always uses the built-in harness.

Composer convenience:

```bash
composer test                # delegates to restify-cli test
php restify-cli test --phpunit --filter=ExampleTest
php restify/tests/run.php    # explicitly invoke the native runner
```

`Restify\Testing\TestCase` boots the full framework, giving you helpers such as `call()`, `json()`, and composed assertions (`Assert::equals`, `Assert::status`, `Assert::json`, etc.). Place additional tests in `tests/` (project root); the runner pulls from both `restify/tests` and your application's `tests` directory.

---

Example Application
-------------------

See the `example` snippets in the previous sections (domain class, `restify/routes/api.php`, `class/App/Blog/PostController.php`, `api/posts.php`, and middleware). Combine them to produce a fully functional demo API.

---

Packages & Contributions
------------------------

Restify-PHP welcomes community extensions. Packages are simple drop-in directories located under `restify/packages`. The autoloader automatically resolves any namespace by translating it into that path.

### Creating a package

1. Create a vendor/package directory, e.g. `restify/packages/Acme/Feature`.
2. Place PSR-4 organised PHP files (`Acme\Feature\Service` → `restify/packages/Acme/Feature/Service.php`).
3. Optionally add a `bootstrap.php` file for initialisation (register routes, middleware, observers, etc.). It is included automatically during bootstrap.
4. Avoid Composer dependencies; keep packages zero-dependency or self-contained.
5. Document usage so teams can drop the folder into their project and go.

### Integrating a package

1. Download/clone the package into `restify/packages/<Vendor>/<Package>`.
2. Review `bootstrap.php` for setup steps and environment keys.
3. Use package classes directly; the autoloader handles availability.
4. Delete the directory to remove the package—no further wiring necessary.

### Contributing upstream

1. Fork the repository.
2. Add the package under `restify/packages/` and update documentation if needed.
3. Add or update tests under `restify/tests/` (run `php restify/tests/run.php`).
4. Open a PR describing the package, namespaces, bootstrap behaviour, and usage.

Packages that follow these guidelines remain portable and easy for other Restify-PHP users to adopt.

---

Hosting & Deployment
--------------------

### Built-In Server

```bash
php restify-cli run --host 127.0.0.1 --port 8000 --docroot public
```

### Docker & docker-compose

Spin up the bundled container images:

```bash
docker compose up --build
# App -> http://localhost:8000, Redis cache -> 6379
```

- `Dockerfile` ships with PHP 8.2 CLI + PDO extensions + opcache.
- `docker-compose.yml` mounts the repository for live reload and provisions a Redis instance (optional caching backend).
- Environment overrides: `RESTIFY_PORT`, `APP_ENV`, `APP_DEBUG`, `APP_URL`.

### Apache

- Point `DocumentRoot` to the `public/` directory.
- Enable `mod_rewrite`.
- Hardened `.htaccess` already supplied for clean URLs, extensionless PHP, and security headers.

### .htaccess Explained

`public/.htaccess`:

- Serves existing files/directories directly.
- Supports extensionless PHP (e.g. `/status` → `status.php`).
- Routes everything else through `index.php`.
- Denies access to `.env`, logs, and tool manifests.
- Disables directory browsing and sets headers (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, etc.).

### Nginx

```nginx
server {
    listen 80;
    server_name restify.local;
    root /var/www/restify/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(env|git|ht) {
        deny all;
    }
}
```

---

Security Checklist
------------------

1. Point your web server to `public/`. Never expose `restify/` or `class/`.
2. Copy `.env.example` to `.env` and protect it.
3. Mirror `.htaccess` security headers in your production server.
4. Validate and sanitise input—route parameter casting helps but does not replace validation.
5. Add a Content-Security-Policy header when serving HTML.
6. Enforce HTTPS behind proxies/load balancers.
7. Review `Async::background()` scripts to avoid user-controlled execution.
8. Lock down filesystem permissions (especially `storage/`).
9. Keep logs sanitised; `.htaccess` blocks `.log` downloads by default.

---

Appendix
--------

### Environment Variables

- Core: `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE`, `APP_URL`, `APP_VERSION`.
- Async toggle: `RESTIFY_ASYNC` (set automatically by the async CLI runner).
- Rate limiting: `RATE_LIMIT_MAX`, `RATE_LIMIT_WINDOW`.
- Authentication: `AUTH_ENABLED`, `AUTH_PUBLIC_PATHS`, `AUTH_HEADER`, `AUTH_SECRET`.
- Logging: `LOGGING_ENABLED`, `LOG_LEVEL`, `LOG_PATH`, `LOG_BODY_LIMIT`, `LOG_REQUEST_BODY`, `LOG_RESPONSE_BODY`, `LOG_DATABASE_ENABLED`, `LOG_SENSITIVE_FIELDS`.
- CORS: `CORS_ENABLED`, `CORS_ALLOWED_ORIGINS`, `CORS_ALLOWED_METHODS`, `CORS_ALLOWED_HEADERS`, `CORS_EXPOSED_HEADERS`, `CORS_ALLOW_CREDENTIALS`, `CORS_MAX_AGE`.
- Exceptions: `EXCEPTIONS_ENABLED`, `EXCEPTIONS_REPORT`, `EXCEPTIONS_TRACE`, `EXCEPTIONS_LOG_LEVEL`.
- Docker overrides: `RESTIFY_PORT` for compose binding.
- Database credentials as detailed in [Database Connectivity](#database-connectivity).

### Schema Registry

- Define reusable JSON Schemas in `restify/config/schemas.php`.
- Reference schemas by name (`'CreateUser'`) within route metadata or attributes.
- Schemas populate validation, OpenAPI components, and example payloads automatically.

### Changelog

- **v1.0.0** – Core runtime, async engine, CLI tooling, route attributes, database helper, OpenAPI generator, package autoloading.

### Contributing

1. Fork the repository.
2. Create a feature branch.
3. Run tests (`php restify/tests/run.php`).
4. Submit a PR describing changes.

### License

Restify-PHP is released under the MIT License. Use it freely in commercial and open-source projects alike.





