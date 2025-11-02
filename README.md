Restify-PHP
===========

Ultra-light, zero-dependency PHP 8+ micro-framework for building blazing fast JSON APIs. Drop your domain objects into `class/`, start the built-in server, and you are shipping without Composer or vendor installs.

Key features:

- Pure PHP 8+ with strict typing and modern paradigms.
- Drop-in packages under `restify/packages`, autoloaded automatically.
- Unified database helper (`Restify\Support\DB`) for MySQL, PostgreSQL, SQL Server, Oracle, SQLite, and MongoDB.
- Built-in request logging and token authentication via CLI-driven setup.
- File-based endpoints in `api/` plus attribute-driven routes inside `class/`.
- Optional Fiber-powered async runtime and background jobs.
- One-command OpenAPI generation with live Swagger UI preview.
- Batteries-included CLI (`php restify-cli`) for serving, scaffolding, documenting, and testing.

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
php restify-cli log
php restify-cli authentication
php restify-cli docs:openapi --serve --port 8081
```

Documentation
-------------

Read the full guide in [`docs/restify.md`](docs/restify.md).
