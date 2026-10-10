# Cetakin Cloud

Cetakin Cloud is a production-oriented software engineering portfolio project for managing Cetakin, a real 3D printing business.

Planning and architecture have been approved for implementation (G0). This checkout implements **CET-001–003** application/development/quality foundations and **CET-004** customer registration, login, logout and ownership-scoped account views. It remains one Laravel + Inertia + React/TypeScript application without SSR. BD-01 v1 is approved (2026-10-10; PRD section 7.1); BD-02–17 remain gated. CET-005 staff/recovery work and later business capabilities have not started.

## Windows container development

Use Docker Desktop Linux containers and PowerShell; host PHP/Composer/Node are optional. Follow [DEVELOPMENT](docs/DEVELOPMENT.md) for isolated worktree setup, locked installation/build, PostgreSQL dev/test targets, migrations and cleanup. Start with `.\scripts\dev.ps1 setup`, then `install`, `build`, `start`, and `migrate`. Open the worktree-specific `.localhost` URL printed by `config` for `/register`, `/login` and `/account`. Each worktree has separate database/session/storage resources and host-only cookies. Run `.\scripts\quality.ps1` before a PR; the GitHub PR/main workflow uses the same gate. No deployment is configured.

## Runtime and bootstrap checks

Use PHP 8.3 or a compatible newer supported PHP release, Composer 2, and Node.js 24 LTS with npm. PHP requires Laravel's standard extensions plus DOM/XML for the bootstrap smoke checks. `composer check-platform-reqs` verifies the installed runtime. The selected direct versions and all resolved dependencies are pinned in `composer.lock` and `package-lock.json`.

`GET /` retains the neutral bootstrap page. `GET /up` returns only `{"status":"ok"}`; it confirms routing, not database/storage health or production readiness. The application now requires migrated PostgreSQL for database sessions and persistent throttles. Existing host `.env` files are preserved; update their session/cache settings if choosing a separately configured native runtime. Local keys and credentials must stay untracked; `APP_DEBUG` is disabled by default.

For host asset development, run `npm run dev` in a second terminal. Stop it and remove an abandoned `public/hot` file before checking the production build. The documented Compose workflow instead uses compiled assets and isolated runtime resources.

The bootstrap smoke suite explicitly uses file sessions/array cache to retain database-free checks. Access tests use guarded PostgreSQL and database sessions/cache. Inertia DevTools recording and automatic filesystem serving/upload routes remain disabled. There is no staff authorization, recovery, email delivery, worker or public API.

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
