# Cetakin Cloud

Cetakin Cloud is a production-oriented software engineering portfolio project for managing Cetakin, a real 3D printing business.

Planning and architecture have been approved for implementation (G0). This checkout implements **CET-001** bootstrap plus **CET-002** local Windows/PostgreSQL infrastructure and **CET-003** testing/quality gates: one Laravel application serving a neutral React/TypeScript page through Inertia, without SSR. Business decisions BD-01–17 remain gated; no customer or staff workflow is implemented.

## Windows container development

Use Docker Desktop Linux containers and PowerShell; host PHP/Composer/Node are optional. Follow [DEVELOPMENT](docs/DEVELOPMENT.md) for isolated worktree setup, locked dependency installation/build, PostgreSQL dev/test targets, safe reset and cleanup. Start with `.\scripts\dev.ps1 setup`, then `install`, `build`, and `start`. Each worktree gets its own environment, ports and runtime volumes. Run `.\scripts\quality.ps1` before a PR; it checks PostgreSQL foundation tests, components, formatting, static/types, build and dependency audits. The GitHub PR/main workflow uses the same gate; no deployment is configured.

## CET-001 setup

Use PHP 8.3 or a compatible newer supported PHP release, Composer 2, and Node.js 24 LTS with npm. PHP requires Laravel's standard extensions plus DOM/XML for the bootstrap smoke checks. `composer check-platform-reqs` verifies the installed runtime. The selected direct versions and all resolved dependencies are pinned in `composer.lock` and `package-lock.json`.

From the repository root in PowerShell, on a fresh checkout:

```powershell
composer install
npm ci --ignore-scripts
Copy-Item .env.example .env
php artisan key:generate
npm run typecheck
npm run build
composer test:bootstrap
php artisan serve --host=127.0.0.1 --port=8000
```

Open `http://127.0.0.1:8000/`. `GET /up` returns only `{"status":"ok"}`; it confirms application routing, not database/storage health or production readiness. Do not overwrite an existing `.env` when repeating setup. Its generated application key is local and must stay untracked. `APP_DEBUG` is disabled by default.

For host asset development, run `npm run dev` in a second terminal. Stop it and remove an abandoned `public/hot` file before checking the production build. The documented Compose workflow instead uses compiled assets and isolated runtime resources.

The bootstrap uses file sessions and an in-memory array cache so its routes need no database. These are transport-only defaults: approved database-backed identity sessions arrive with CET-004. CET-002 adds PostgreSQL connection/configuration and migration bookkeeping only; no business schema, authentication, worker or public API exists. Inertia DevTools recording and automatic filesystem serving/upload routes are disabled. The four bootstrap smoke tests are database-free checks, not the PostgreSQL-backed F layer in [TEST_STRATEGY](docs/TEST_STRATEGY.md).

## Module ownership convention

Follow [ARCHITECTURE](docs/ARCHITECTURE.md) when real capabilities arrive. Create namespaces and feature folders only when used; this bootstrap creates no empty module hierarchy.

| Responsibility | Owns |
| --- | --- |
| Access | Customer/actor relationships, current ownership and task permissions |
| Intake & Quotations | Requests, review, specifications, quote versions and agreement evidence |
| Operations | Orders, jobs, truthful production facts and physical outcomes |
| Payments | Verified external-money events, corrections and derived summaries |
| File Handling | Private bytes, exact version provenance, availability and permitted deletion |

Thin HTTP boundaries map input and explicit page projections. Named actions coordinate necessary business outcomes; each module owns its writes. Intake/Operations may read exact file availability; Operations/Payments may read the fixed agreement basis. Payments cannot change physical state, Files cannot rewrite agreed scope, and React cannot bypass server authorization. Do not add generic repositories, service-per-entity layers or speculative domain classes.
