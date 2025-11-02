Restify-PHP
===========

Simplicity | Performance | Portability

Ultra-light, zero-dependency PHP 8+ micro-framework for building blazing fast JSON APIs. Drop your domain objects into `class/`, add procedural endpoints in `api/`, start the built-in server, and you are shipping without Composer or vendor installs.

Why Restify?
============

- Pure PHP 8+ with strict typing, PSR-12 style, and no bundled dependencies.
- File-first development: `class/` for domain logic and attributes, `api/` for endpoint scripts, `restify/` for framework internals.
- Async ready: Fibers, curl-multi, non-blocking sockets, and background workers useable with `php restify-cli run --async`.
- Drop-in middleware: CORS, rate limiting, logging with redaction, unified exception handler, JWT auth, request validation.
- Configurable caching via APCu/opcache, DB helpers for MySQL, PostgreSQL, SQL Server, Oracle, SQLite, MongoDB.
- OpenAPI generator produces JSON/YAML specs with example payloads and spun-up Swagger UI.
- Built-in tests, CLI scaffolding, install command, and Docker/docker-compose templates for instant bootstrapping.
- Health check at `/health` for orchestrators plus `/docs` generation for humans.

Quick start
-----------

```bash
php -S localhost:8000 -t public
```

Composer install
----------------

```bash
composer require restify-php/restify-php
php vendor/bin/restify install
php restify-cli run --async     # optional fiber runtime
```

Command line helpers
--------------------

```bash
php restify-cli --help
php restify-cli make:class App\Domain\User
php restify-cli run --async
php restify-cli docs:openapi --format yaml --serve --port 8081
php restify-cli log
php restify-cli authentication
php restify-cli test --phpunit   # falls back to native runner when phpunit missing
```

Composer scripts
----------------

```bash
composer serve          # php -S 0.0.0.0:8000 -t public
composer docs           # openapi generation
composer test           # delegates to restify-cli test
```

Docker
------

```bash
docker compose up --build
# App available at http://localhost:8000, Redis exposed on 6379 (optional caching)
```

Environment snippet
-------------------

`.env` carries sane defaults; tweak as needed:

```
LOGGING_ENABLED=true
LOG_LEVEL=info
LOG_PATH=storage/logs/restify.log
LOG_BODY_LIMIT=2048
CORS_ENABLED=true
CORS_ALLOWED_ORIGINS=*
AUTH_ENABLED=true
AUTH_PUBLIC_PATHS=/health
EXCEPTIONS_TRACE=false
```

Health check
------------

`GET /health` returns:

```json
{
  "status": "ok",
  "timestamp": "2025-01-01T00:00:00+00:00"
}
```

Documentation
-------------

The complete guide (architecture, CLI, async, auth, caching, validation, OpenAPI, middleware, packaging) lives in [`docs/restify.md`](docs/restify.md).
