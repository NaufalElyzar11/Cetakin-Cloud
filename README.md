# Cetakin Cloud

Cetakin Cloud is a production-oriented software engineering portfolio project for managing Cetakin, a real 3D printing business.

Planning and architecture have been approved for implementation (G0). CET-001–005 provide the application foundation and customer/staff Access. CET-006/007 provide customer-owned custom FDM requests, exact private STL/3MF files, compatible object storage and bounded intent reconciliation. **CET-008** adds an Owner review work list, request-scoped review/file access, attributable manual findings, responsibility/next action and terminal decline, using synthetic records/objects only. It remains one Laravel + Inertia + React/TypeScript application without SSR. BD-01 v1 (2026-10-10) and BD-02/03/05 v1 (2026-10-12) are approved in the PRD; BD-04 and BD-06–17 remain OPEN. Real customer-design intake, operational deletion and production reliance are not authorized; BD-13's operational error/hold procedure remains a gate. Clarification replies/replacements, quotations and production are not implemented.

## Windows container development

Use Docker Desktop Linux containers and PowerShell; host PHP/Composer/Node are optional. Follow [DEVELOPMENT](docs/DEVELOPMENT.md) for isolated worktree setup, locked installation/build, PostgreSQL dev/test targets, migrations and cleanup. Start with `.\scripts\dev.ps1 setup`, then `install`, `build`, `start`, and `migrate`. Open the worktree-specific `.localhost` URL printed by `config` for `/register`, `/login`, `/account` and `/requests`. Each worktree has separate database/session/storage resources and host-only cookies. Run `.\scripts\quality.ps1` before a PR; the GitHub PR/main workflow uses the same gate. No deployment is configured.

Local private storage remains the default. The quality gate starts an isolated, pinned SeaweedFS compatible test service and verifies authenticated/private conditional writes. See DEVELOPMENT for `storage-start`, optional synthetic adapter selection, scoped test cleanup and the limits of this compatibility evidence; no production storage vendor is selected.

## Runtime and bootstrap checks

Use PHP 8.3 or a compatible newer supported PHP release, Composer 2, and Node.js 24 LTS with npm. PHP requires Laravel's standard extensions plus DOM/XML for the bootstrap smoke checks. `composer check-platform-reqs` verifies the installed runtime. The selected direct versions and all resolved dependencies are pinned in `composer.lock` and `package-lock.json`.

`GET /` retains the neutral bootstrap page. `GET /up` returns only `{"status":"ok"}`; it confirms routing, not database/storage health or production readiness. The application now requires migrated PostgreSQL for database sessions and persistent throttles. Existing host `.env` files are preserved; update their session/cache settings if choosing a separately configured native runtime. Local keys and credentials must stay untracked; `APP_DEBUG` is disabled by default.

For host asset development, run `npm run dev` in a second terminal. Stop it and remove an abandoned `public/hot` file before checking the production build. The documented Compose workflow instead uses compiled assets and isolated runtime resources.

Use synthetic STL/3MF files only: select an authorized Customer, PETG, an approved color and a positive whole quantity of complete products/sets. The selected set is 1–5 files, at most 25 MiB each and 75 MiB combined. Confirmation means Submitted, awaiting manual review. Keep the attempt URL to check unknown outcomes or retry identical work; transfer progress alone is not confirmation. Uploaded bytes stay private and downloads check current access. See DEVELOPMENT for format/security bounds and failure behavior.

An Owner can open `/staff/requests` and explicitly assign an actual request to a current Owner before examining its private designs or recording review/decline. An assignment confers no staff responsibility. A sufficient review records manual findings against exact files; it does not quote or approve production. Customers see only their permitted requests and safe outcomes. Follow DEVELOPMENT for the synthetic review/decline and revocation checklist.

The bootstrap smoke suite explicitly uses file sessions/array cache to retain database-free checks. Access and intake database cases use guarded PostgreSQL; pure rules/content cases and focused components supplement them. Follow DEVELOPMENT for the trusted CLI provisioning/recovery runbook and Owner-only responsibility management. Operator responsibility grants no access to absent future work records. Inertia DevTools recording and automatic filesystem serving/upload routes remain disabled. There is no email delivery, self-service reset, worker or public API.

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
