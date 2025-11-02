Restify-PHP Documentation
=========================

> **Restify-PHP** is a native PHP 8+ micro-framework focused on simplicity, performance, and zero dependencies. Drop it into any environment, point the server at `public/`, and build APIs without Composer, scaffolding, or heavy bootstrapping.

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
10. [Logging & Observability](#logging--observability)
11. [Authentication](#authentication)
12. [Command-Line Interface](#command-line-interface)
13. [Testing](#testing)
14. [Example Application](#example-application)
15. [Packages & Contributions](#packages--contributions)
16. [Hosting & Deployment](#hosting--deployment)
17. [Security Checklist](#security-checklist)
18. [Appendix](#appendix)
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

- Pure PHP 8+ with strict typing and modern language features.
- Automatic eager-loading of every class in `class/`.
- File-based endpoints under `api/` with per-method helpers.
- Unified JSON response envelope.
- Optional Fiber-based async engine for concurrent HTTP, sockets, and background tasks.
- Turn-key OpenAPI generator with Swagger UI preview.
- Drop-in package system (`restify/packages`) for sharing functionality without Composer.
- Batteries-included CLI (`php restify-cli`) for serving, scaffolding, documenting, and testing.

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

### CLI Shortcuts

```bash
php restify-cli --help
php restify-cli run --host 0.0.0.0 --port 8080 --async
php restify-cli make:class App\\Domain\\UserService
php restify/tests/run.php
php restify-cli docs:openapi --serve --port 8081
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

Global middleware resides in `restify/config/middleware.php`:

```php
<?php

use Restify\Http\Request;
use Restify\Http\Response;

return [
    'global' => [
        static function (Request $request, callable $next): Response {
            $response = $next($request);
            $response->headers['X-Powered-By'] = 'Restify-PHP';

            return $response;
        },
    ],
];
```

- Class middleware must implement `Restify\Middleware\MiddlewareInterface`.
- Closures receive the `Request` and `$next` callable.

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

Logging & Observability
-----------------------

Restify ships with a lightweight request logger that records inbound traffic once the backing table exists.

### Enabling logging

1. Ensure your database connection is configured in `.env`.
2. Run `php restify-cli log` to create the required `restify_logs` (and companion `restify_tokens`) tables.
3. `Restify\Middleware\LoggingMiddleware` automatically records every request after the response is generated.

### Logged fields

- `endpoint` – request path (e.g. `/api/posts/1`)
- `request_method` – HTTP method (`GET`, `POST`, etc.)
- `user_data` – JSON containing IP address, user agent, and related metadata
- `status_code` – HTTP status returned to the client
- `created_at` – timestamp of the log entry

Example query:

```sql
SELECT endpoint, request_method, status_code, created_at
FROM restify_logs
ORDER BY id DESC
LIMIT 25;
```

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

---

Command-Line Interface
----------------------

Use `php restify-cli` to interact with your project:

| Command            | Description                                                    | Usage Example                                           |
|--------------------|----------------------------------------------------------------|---------------------------------------------------------|
| `run`              | Serve the application with optional async runtime.             | `php restify-cli run --host 0.0.0.0 --port 9000 --async` |
| `make:class`       | Generate a class stub inside `class/`.                         | `php restify-cli make:class App\\Services\\Billing`     |
| `log`              | Initialise logging and authentication tables.                  | `php restify-cli log`                                   |
| `authentication`   | Issue authentication tokens tied to specific endpoints.        | `php restify-cli authentication`                        |
| `docs:openapi`     | Generate OpenAPI docs and optional Swagger UI live preview.    | `php restify-cli docs:openapi --serve --port 8081`      |

Helpful flags:

- `php restify-cli --help`
- `php restify-cli help run`
- `php restify-cli help log`
- `php restify-cli help authentication`
- `php restify-cli docs:openapi --help`

---

Testing
-------

The bundled runner lives at `restify/tests/run.php`. Every test extends `Restify\Testing\TestCase`, which boots the full application.

```bash
php restify/tests/run.php
```

Assertions include `Assert::equals`, `Assert::status`, `Assert::json`, and more.

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

- `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE`, `APP_URL`
- `APP_VERSION` (optional, used in OpenAPI docs)
- `RESTIFY_ASYNC` (automatically toggled when running the async server command)
- Database keys as described in [Database Connectivity](#database-connectivity)

### Changelog

- **v1.0.0** – Core runtime, async engine, CLI tooling, route attributes, database helper, OpenAPI generator, package autoloading.

### Contributing

1. Fork the repository.
2. Create a feature branch.
3. Run tests (`php restify/tests/run.php`).
4. Submit a PR describing changes.

### License

Restify-PHP is released under the MIT License. Use it freely in commercial and open-source projects alike.
