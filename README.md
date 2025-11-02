Restify-PHP
===========

Ultra-light, zero-dependency PHP 8+ micro-framework for building blazing fast JSON APIs. Clone it, drop your domain objects into `class/`, start the built-in server, and you are in business—no Composer, no external packages.

Key features:

- Pure PHP 8+ with strict typing and modern paradigms.
- Drop-in packages live in `restify/packages` and are auto-loaded.
- File-based endpoints under `api/` with per-method handlers.
- Unified request/response pipeline with optional Fiber-powered async runtime.
- Attribute-powered routing directly inside class files, alongside `restify/routes/web.php` and `restify/routes/api.php`.
- Hardened `.htaccess` for Apache deployments and extensionless URLs out of the box.
- Batteries-included CLI (`php restify-cli`) for serving and scaffolding.
- Lightweight testing harness with assertions.

Get Started
-----------

```bash
php -S localhost:8000 -t public
```

Command line helpers:

```bash
php restify-cli --help
php restify-cli run --async
php restify-cli make:class App\Domain\Example
php restify/tests/run.php
```

Documentation
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
9. [Command-Line Interface](#command-line-interface)
10. [Testing](#testing)
11. [Example Application](#example-application)
12. [Packages & Contributions](#packages--contributions)
13. [Hosting & Deployment](#hosting--deployment)
    - [Built-In Server](#built-in-server)
    - [Apache](#apache)
    - [.htaccess Explained](#htaccess-explained)
    - [Nginx](#nginx)
14. [Security Checklist](#security-checklist)
15. [Appendix](#appendix)
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
- Bundled CLI (`php restify-cli`) for serving, scaffolding, and async toggles.
- Lightweight unit test harness with first-class assertions.

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
php restify-cli make:class App\Domain\UserService
php restify/tests/run.php
```

### API Prefix Convention

The framework automatically loads `restify/routes/web.php` and `restify/routes/api.php`. Define public-facing views in `web.php` and REST endpoints (usually prefixed with `/api`) in `api.php`. Your server should point the document root at the `public/` directory, so end-users access `/` or `/api/...` without a `/public` suffix.

---

Project Structure
-----------------

```
restify/        # Framework internals (bootstrap, config, routes, src, support, tests)
    bootstrap/  # Application bootstrapper
    config/     # Framework configuration (middleware etc.)
    routes/     # Route declarations (web.php, api.php, attributes)
    src/        # Restify core
    support/    # Developer-facing helpers (Async facade)
    tests/      # Lightweight test runner & specs
    packages/   # Optional drop-in packages (bootstrap + PSR-4 sources)
api/            # File-based API endpoints (auto-registered)
class/          # Your domain code (autoloaded & globally available)
docs/           # Documentation (this file)
public/         # Web document root (contains index.php & .htaccess)
storage/        # Logs, cache, temp directories
restify-cli     # Optional CLI entry script
```

- Place reusable logic inside `class/`. Restify loads every `.php` file in this directory at startup, mimicking Python’s `__init__.py`.
- Keep only front-controller assets inside `public/` (`index.php`, `.htaccess`, static files).
- Treat everything under `restify/` as framework internals—modify with care.

---

Runtime Architecture
--------------------

1. `public/index.php` boots the application via `restify/bootstrap/app.php`.
2. `restify/bootstrap/app.php` defines `RESTIFY_BASE_PATH` (framework) and `RESTIFY_ROOT_PATH` (project), registers the custom autoloader, loads `.env`, configures PHP, and returns `Restify\Core\Application`.
3. `Application`:
   - Loads global middleware from `restify/config/middleware.php`.
   - Registers routes from `restify/routes/web.php`, `restify/routes/api.php`, class attributes, and the `api/` directory.
4. Requests are wrapped in immutable `Restify\Http\Request` objects and flow through the `MiddlewarePipeline`.
5. The `Router` matches paths and runs handlers. Output is normalized into `Restify\Http\Response`, ensuring a consistent JSON envelope.

---

Routing
-------

### Route Files

Restify automatically loads two route files if present:

- `restify/routes/web.php` – default web endpoints.
- `restify/routes/api.php` – API-specific routes (commonly prefixed with `/api`).

Each file returns a closure receiving the shared `Router` instance.

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

Prefer configuration arrays? Return an associative array instead:

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
- Handlers receive the request and route parameters just like standard router closures.

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

    private function all(): array { /* ... */ }
    private function find(int $id): ?array { /* ... */ }
}
```

Rules:

- Methods can be `public` instance methods (constructor must have no required arguments) or `public static`.
- Signatures support arguments such as `Request`, `array $params`, and scalar route parameters (`int $id`).
- Exceptions from handlers bubble up like standard closures.

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
  "data": { /* payload */ },
  "meta": { /* optional metadata */ }
}
```

---

Responses
---------

Use `Restify\Http\Response` helpers directly:

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

The optional async engine lives in `support/Async.php` (under `restify/support/`) and wraps a Fiber-based event loop.

Core capabilities:

- `Async::run(callable $callback)` – execute within the event loop; falls back to synchronous execution if Fibers are unavailable.
- `Async::parallel(array $callbacks)` – run multiple closures concurrently (Fibers) or sequentially (fallback).
- `Async::http(string|array $request, array $options = [])` – concurrent HTTP operations via `curl_multi`.
- `Async::socket(string $host, int $port, string $payload = '', float $timeout = 5.0)` – non-blocking socket reads using `stream_select`.
- `Async::background(string $script, array $arguments = [])` – fire-and-forget PHP subprocesses (resolved relative to the project root).
- `Async::json(array $data, int $status = 200, array $meta = [], ?string $message = null)` – convenience response helper.

Enable async mode automatically via the CLI:

```bash
php restify-cli run --async
```

If Fibers, Swoole, or coroutine extensions are unavailable, Restify logs a warning and continues in synchronous mode.

---

Command-Line Interface
----------------------

Use `php restify-cli` to interact with your project:

| Command      | Description                                        | Usage Example                                        |
|--------------|----------------------------------------------------|------------------------------------------------------|
| `run`        | Serve the application with optional async runtime. | `php restify-cli run --host 0.0.0.0 --port 9000 --async` |
| `make:class` | Generate a class stub inside `class/`.             | `php restify-cli make:class App\Services\Billing`      |

Helpful flags:

- `php restify-cli --help`
- `php restify-cli help run`
- `php restify-cli make:class --help`

`run` accepts `--host`, `--port`, `--docroot`, and `--async` to customise the built-in server.

---

Testing
-------

The bundled runner lives at `restify/tests/run.php`. Every test extends `Restify\Testing\TestCase`, which boots the full application so you can issue HTTP-like calls.

```php
<?php

declare(strict_types=1);

namespace Tests;

use Restify\Testing\Assertions\Assert;
use Restify\Testing\TestCase;

final class HealthTest extends TestCase
{
    public function testHealthEndpoint(): void
    {
        $response = $this->call('GET', '/api/health');

        Assert::status($response, 200);
        Assert::json($response);
    }
}
```

Run the suite:

```bash
php restify/tests/run.php
```

---

Example Application
-------------------

The following mini-blog demonstrates route files, class-based routes, API endpoints, middleware, and async helpers.

### 1. Domain Class

`class/App/Blog/PostRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Blog;

final class PostRepository
{
    private array $posts = [
        ['id' => 1, 'title' => 'Hello Restify', 'body' => 'Born lightweight.'],
        ['id' => 2, 'title' => 'Async optional', 'body' => 'Fibers when you need them.'],
    ];

    public function all(): array
    {
        return $this->posts;
    }

    public function find(int $id): ?array
    {
        foreach ($this->posts as $post) {
            if ($post['id'] === $id) {
                return $post;
            }
        }

        return null;
    }
}
```

### 2. API Routes

`restify/routes/api.php`

```php
<?php

use Restify\Http\Request;
use Restify\Routing\Router;

return static function (Router $router): void {
    $repository = new App\Blog\PostRepository();

    $router->get('/api/posts', static fn () => ['posts' => $repository->all()]);

    $router->get('/api/posts/{id}', static function (Request $request, array $params) use ($repository) {
        $post = $repository->find((int) $params['id']);

        if (!$post) {
            return Restify\Http\Response::json([], 404, message: 'Post not found');
        }

        return ['post' => $post];
    });
};
```

### 3. Class-Based Async Route

`class/App/Blog/PostController.php`

```php
<?php

declare(strict_types=1);

namespace App\Blog;

use Restify\Routing\Attributes\Route;
use Restify\Support\Async;

final class PostController
{
    #[Route('GET', '/api/posts/remote')]
    public static function remote(): \Restify\Http\Response
    {
        return Async::run(static function () {
            $responses = Async::parallel([
                static fn () => Async::http('https://jsonplaceholder.typicode.com/posts/1'),
                static fn () => Async::http('https://jsonplaceholder.typicode.com/posts/2'),
            ]);

            return Async::json([
                'first' => json_decode($responses[0]['body'], true),
                'second' => json_decode($responses[1]['body'], true),
            ]);
        });
    }
}
```

### 4. File-Based API Endpoint

`api/posts.php`

```php
<?php

use Restify\Http\Request;

$api->get(static fn () => ['posts' => (new App\Blog\PostRepository())->all()]);

$api->post(static function (Request $request) {
    return ['created' => $request->body];
});
```

### 5. Middleware

`restify/config/middleware.php`

```php
<?php

use Restify\Http\Request;
use Restify\Http\Response;

return [
    'global' => [
        static function (Request $request, callable $next): Response {
            $response = $next($request);
            $response->headers['X-Powered-By'] = 'Restify-Blog';

            return $response;
        },
    ],
];
```

---

Packages & Contributions
------------------------

Restify-PHP welcomes community extensions. Packages are simple drop-in directories located under `restify/packages`. The autoloader automatically resolves any namespace by translating it into that path, so registering a package is as easy as copying it into the project.

### Creating a package

1. Create a vendor/package directory under `restify/packages`, for example `restify/packages/Acme/Feature`.
2. Place your source files using PSR-4 style namespaces. `Acme\Feature\Service` maps to `restify/packages/Acme/Feature/Service.php`.
3. Optionally add a `bootstrap.php` file inside the package root to run initialisation logic (register routes, middleware, observers, etc.). This file is loaded automatically during bootstrap if present.
4. Keep dependencies zero or self-contained; packages should not rely on Composer.
5. Provide documentation for usage so other teams can drop the folder into their project and go.

### Integrating a package

1. Download or clone the package into `restify/packages/<Vendor>/<Package>`.
2. Review any `bootstrap.php` for setup steps and environment keys.
3. Call package classes directly inside routes, API files, or domain code—the autoloader makes them instantly available.
4. When removing a package, delete its directory; no additional configuration is required.

### Contributing upstream

1. Fork the repository.
2. Add your package under `restify/packages/` and, if needed, update documentation.
3. Add or update tests under `restify/tests/` that exercise your package (use `php restify/tests/run.php`).
4. Open a pull request describing the package, its namespace, and its bootstrap behaviour.

Packages that follow these guidelines remain portable and easy for other Restify-PHP users to adopt.

---

Hosting & Deployment
--------------------

### Built-In Server

Use the built-in PHP server for local development:

```bash
php restify-cli run --host 127.0.0.1 --port 8000 --docroot public
```

### Apache

- Point `DocumentRoot` to the `public/` directory.
- Enable `mod_rewrite`.
- Restify ships with a hardened `.htaccess` to:
  - Rewrite clean URLs (`/api/users` → `index.php`).
  - Allow extensionless PHP files (`/status` resolves to `status.php`).
  - Block access to `.env`, logs, and package manifests.
  - Disable directory browsing and set security headers.

Example virtual host:

```apacheconf
<VirtualHost *:80>
    ServerName restify.local
    DocumentRoot "/var/www/restify/public"

    <Directory "/var/www/restify/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### .htaccess Explained

`public/.htaccess` includes:

- Rewrites to serve existing files and directories directly.
- Extensionless PHP support (e.g. `/status` resolves to `status.php`).
- Catch-all rewrite to `index.php`.
- Security guards denying access to `.env`, `.log`, and tool manifests.
- HTTP header hardening (`X-Frame-Options`, `X-Content-Type-Options`, etc.).
- `Options -Indexes` to block directory listing.

### Nginx

Example configuration:

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

With this setup, end-users never see `/public` in the URL; `/` and `/api/*` are served directly.

---

Security Checklist
------------------

1. **Document root** – Always point your web server to `public/`. Never expose the `restify/` directory or the `class/` directory.
2. **Environment** – Copy `.env.example` to `.env` and forbid public access (already handled by `.htaccess` and the sample Nginx config).
3. **Headers** – `.htaccess` sets secure defaults; mirror them in Nginx or other servers.
4. **Input validation** – Route parameter casting helps, but sanitise and validate request payloads explicitly.
5. **Content-Security-Policy** – Add CSP headers if serving HTML responses.
6. **HTTPS** – Terminate TLS at the proxy/load balancer; force HTTPS where appropriate.
7. **Async background tasks** – Ensure background scripts invoked via `Async::background()` are sanitised and not user-controlled.
8. **File permissions** – Deny write access to `public/` and sensitive directories in production (except for upload directories you control).
9. **Logging** – Use sanitised logs (avoid writing secrets). `.htaccess` blocks `.log` downloads by default.

---

Appendix
--------

### Environment Variables

`.env` keys are loaded into `$_ENV`, `$_SERVER`, and `Application::env()`. Common keys:

- `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_TIMEZONE`, `APP_URL`
- `RESTIFY_ASYNC` (automatically toggled when running `php restify-cli run --async`)

### Changelog

- **v1.0.0**
  - Core runtime, async engine, CLI tooling, route attributes, `.htaccess` security hardening, package autoloading.

### Contributing

1. Fork the repository.
2. Create a feature branch.
3. Write tests (`php restify/tests/run.php`).
4. Submit a PR describing your changes.

Community extensions (middlewares, route attributes, packages, test utilities) are welcome—Restify stays zero-dependency, but recipes and patterns help everyone.

### License

Restify-PHP is released under the MIT License. Use it freely in commercial and open-source projects alike.
