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
-------------

Read the full guide in [`docs/restify.md`](docs/restify.md).
