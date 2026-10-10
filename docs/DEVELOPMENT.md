# Windows development — CET-001–004

This is local infrastructure, not a production deployment. Two long-running services are sufficient: a PHP CLI Laravel development server with Composer, and PostgreSQL. Node is a disposable build tool, not another application server. CET-004 adds customer identity/account access, database sessions and database-backed throttles to the existing quality gate. No staff/recovery behavior, later business schema, worker, Redis, object emulator or deployment is added.

## Prerequisites

Windows PowerShell 5.1 or PowerShell 7; Git; Docker Desktop running **Linux containers** with Docker Compose v2. Docker Desktop may use WSL internally; normal commands below run in PowerShell without entering WSL. Docker Desktop/WSL installation and host changes are the developer's responsibility. Enable access to the checkout drive if Docker requests it. Host PHP, Composer and Node are unnecessary for the container workflow.

Current images are PHP `8.3.35-cli-bookworm`, Composer `2.10.3`, PostgreSQL `18.6-trixie`, and Node `24.21.0-bookworm-slim`. They are explicit supported versions checked against the existing dependency locks; tags do not promise immutable OS packages. PostgreSQL uses the official Debian Trixie variant of the same selected 18.6 release; a damaged local Bookworm image layer blocked validation even after a targeted re-pull, without justifying a database-version or architecture change. Node's Debian/glibc slim image avoids unused compiler/tool packages and reduces local image footprint; locked install/type/build checks must pass on it. Refresh images deliberately and revalidate. PostgreSQL 18 mounts its project volume at `/var/lib/postgresql`; its data directory is `/var/lib/postgresql/18/docker`.

## First setup

From the checkout root in PowerShell:

```powershell
docker version
docker compose version
docker context use desktop-linux
.\scripts\dev.ps1 setup
.\scripts\dev.ps1 config
.\scripts\dev.ps1 install
.\scripts\dev.ps1 build
.\scripts\dev.ps1 start
.\scripts\dev.ps1 status
.\scripts\dev.ps1 db-check
.\scripts\dev.ps1 migrate
.\scripts\dev.ps1 test-db-check
.\scripts\dev.ps1 test-reset -ConfirmTestReset
.\scripts\dev.ps1 test
```

Use your normal permitted script-execution policy; if scripts are blocked, review the script and invoke it using a process-only policy, e.g. `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\dev.ps1 config`. This does not change the machine's execution policy.

`setup` creates ignored `.env.docker` with a fresh local app key, per-checkout identity and automatically chosen loopback ports. It never overwrites an environment. Existing host `.env` is preserved and not used for Compose interpolation. Compose explicitly sets the local application/database values; no production values belong in either local file. `setup` and `config` report `http://cetakin-<worktree-id>.localhost:<port>`. Use that hostname for browser account flows; `127.0.0.1:<port>/up` remains suitable for health probes. Health proves routing only, not PostgreSQL readiness or production readiness.

`install` builds PHP with `pdo_pgsql`, the existing app extensions and Composer, then installs **committed locks** into isolated volumes. `build` typechecks and builds production-format assets into ignored checkout-local `public/build`. Re-run it after frontend changes. This deliberately uses compiled assets without a persistent Vite/HMR service or extra exposed port. Host HMR remains an optional CET-001 workflow, not part of this Compose topology; stop it/remove stale `public/hot` before testing compiled assets.

Run `migrate` before opening identity pages. The CET-004 migration adds only `users`, `customers`, `customer_user`, `sessions` and `cache`; there are no later business or staff tables. The pair-keyed relationship supports more than one Customer per actor and more than one actor per Customer without management/onboarding features. PostgreSQL foreign keys preserve valid relationships, and login identifiers have canonical/unique constraints. `sessions` is Laravel's server-side session persistence; `cache` supports throttles across HTTP requests. No queue or cache-lock table is needed by these flows. `test` retains the database-free bootstrap suite and discovers foundation plus Access tests against the guarded test target.

## Database targets and safe reset

Each worktree has its own PostgreSQL cluster/volume, with `cetakin_dev` and `cetakin_test` databases. Their same-named login roles are non-superusers with no database/role creation privileges. Public connection rights are revoked. Network authentication permits each role only its corresponding database and denies administrative network login. Local-only example passwords are deliberately recognizable, not production credentials. If changing them, do so before first initialization; changing an env file does not rotate existing database roles.

The application uses `postgres:5432` inside its private project network. An optional host database client uses loopback plus the reported database port and the corresponding local role. The test role cannot connect to the development database, and the development role cannot connect to the test database. PostgreSQL has no persistent host bind-directory/data dump in the repository.

Use **only the documented wrapper** for test resets. `test-reset -ConfirmTestReset` supplies test credentials and `APP_ENV=testing`, verifies local Docker Desktop/worktree identity, rejects cached/alternate URL targeting, confirms the live database/user/non-superuser identity, then invokes `migrate:fresh` in that same guarded process. It can destroy this worktree's test data, never development data. `test-db-check` performs the same target checks without reset. `migrate` is non-destructive, guarded development migration. Do not run unguarded `migrate:fresh`/`db:wipe`, export production credentials, or copy config caches between environments. This is not protection against a developer deliberately bypassing Docker/database controls.

```powershell
.\scripts\dev.ps1 config-clear
.\scripts\dev.ps1 db-check
.\scripts\dev.ps1 test-db-check
.\scripts\dev.ps1 test-reset -ConfirmTestReset
```

No SQLite connection is defined by this project. The guarded PostgreSQL suite includes independent-connection smoke and synthetic Access fixtures. It does not certify future business races or storage features.

## Worktrees and resource ownership

The wrapper derives `cetakin-<12-character SHA-256 prefix>` from the canonical absolute checkout path (case-normalized on Windows; case-preserving on Linux CI). `.env.docker` must match that worktree. Project-scoped network, PostgreSQL, vendor, node_modules, storage and bootstrap-cache volumes have no fixed global names or `container_name`. Ports default to distinct hash-derived ranges and bind only `127.0.0.1`. Collisions fail rather than silently reusing a listener; choose explicit unused ports when needed:

```powershell
.\scripts\dev.ps1 setup -AppPort 28001 -DatabasePort 38001
```

Each separate worktree runs its own `setup/install/build/start/migrate` and has separate test credentials/targets and runtime volumes. Session cookies are project-named and all session/CSRF cookies are host-only. The distinct `.localhost` hostnames isolate Laravel's fixed `XSRF-TOKEN` cookie; different ports alone cannot do that. Checkout-local asset output and source are never shared. Container private files live in that project's `app_storage` volume at `/workspace/storage/app/private`; they are **not** the host CET-001 `storage` directory. Do not put confidential real designs into this foundation environment. The shared Git object/history store is not runtime data.

Copying `.env.docker` to another worktree is rejected. Moving a checkout changes its identity: stop the old environment from its original path before moving, generate a new environment in the new path, and explicitly migrate any desired synthetic data. Never rename project/volume identifiers to borrow another worktree's resources. The wrapper rejects `DOCKER_HOST` and non-local Docker endpoints; unset remote overrides before using it. Windows requires the Docker Desktop Linux named pipe; Linux CI requires its local Unix socket.

## Daily commands and cleanup

```powershell
.\scripts\dev.ps1 start       # rebuild if Dockerfile changed; wait for health
.\scripts\dev.ps1 status
.\scripts\dev.ps1 logs
.\scripts\dev.ps1 install     # re-install locks after dependency changes
.\scripts\dev.ps1 build
.\scripts\dev.ps1 test
.\scripts\dev.ps1 stop        # removes only this project's containers/network; volumes persist
.\scripts\dev.ps1 start       # persisted dev/test data and storage return
```

Before deleting an unneeded validation worktree, explicitly remove **its** local volumes:

```powershell
.\scripts\dev.ps1 remove -ConfirmRemoveVolumes
```

That final command deletes only this worktree project's databases, private container storage, dependencies and bootstrap cache. It does not remove source files, other projects or their volumes. Do not use global Docker prune commands for worktree cleanup. Dependencies need `install` again after volume removal.

## Troubleshooting

- Engine unavailable/access denied: start Docker Desktop, select Linux containers, and ensure your Windows account may access its named pipe. A Docker CLI alone is insufficient.
- Port occupied: stop the owning process or generate a new local env with explicit unused ports; do not stop an unknown project.
- Empty browser page/missing assets: run `install`, then `build`; ensure no abandoned `public/hot`. PHP startup needs vendor dependencies.
- Config cache rejected: use `config-clear` in this checkout; do not import another environment's cache. Alternate `APP_CONFIG_CACHE` paths must also be cleared or removed from local configuration.
- Login/init failure: PostgreSQL init runs only on an empty volume. Restore the original local passwords or deliberately remove this project's disposable volumes and initialize again. Never reset other projects.
- Slow bind-mount I/O on Windows: Docker Desktop's file sharing can be slower on Windows drives; named dependency/storage volumes avoid the busiest paths. Moving source to another location requires a new project identity, not shared volumes.
- Images/extensions unavailable: report the actual installation failure; do not replace PostgreSQL tests with SQLite or claim validation passed.

## CET-002 validation record — 2026-10-07

Validated on Windows with Docker Desktop's local `desktop-linux` named-pipe endpoint, Linux engine 28.4.0 and Compose 2.39.4. Setup/config work in Windows PowerShell 5.1 and PowerShell 7. The image versions above passed locked Composer installation/platform checks, `npm ci --ignore-scripts`, TypeScript checking and the production Vite build. Live `/`, compiled JavaScript and exact `{"status":"ok"}` health responses returned HTTP 200. The four CET-001 database-free tests passed with 22 assertions in the container and on the existing Windows PHP runtime.

`db-check`, `migrate`, `test-db-check` and confirmed `test-reset` verified PostgreSQL 18.6, explicit database/user identity and clean migration bookkeeping. Development credentials in test-reset, production environment, inherited database URL and an actual alternate configuration-cache fixture were rejected. Test-to-development and development-to-test logins were denied. Remote Docker overrides and unconfirmed resets were rejected. This is infrastructure smoke evidence, not CET-003's PostgreSQL feature/concurrency harness.

A temporary second real Git worktree used a distinct Compose project, ports, databases, dependency/storage/cache volumes and environment. Resetting the first test database left both development databases, the second test database and private-file markers intact. Stopping the first project preserved its volumes while the second remained usable and passed its bootstrap tests. Restart restored the first project's persisted data/storage. Temporary SQL/file probes were removed; both first-worktree databases contained only migration bookkeeping afterward. Git ignore and protected-planning-document checks passed.

Initial image downloads encountered a Docker Desktop failure/EOF. After restarting Desktop, an unusable cached PostgreSQL Bookworm image still had empty executables after a targeted re-pull; the supported same-version Trixie image was verified and used successfully. No unrelated Docker data was globally pruned and no host software was installed. The exact cause of the Desktop failure was not established.

Independent review found no BLOCKER/HIGH issue. Final cleanup exposed a MEDIUM issue: an inactive tools profile left the Node dependency volume behind. The confirmed removal command now includes that profile; rerunning it removed every temporary-project container/volume while the first application's health stayed successful. The temporary Git worktree was also removed. A LOW follow-up remains: different localhost ports share the framework's `XSRF-TOKEN` cookie namespace even though transport session cookies are isolated. Current routes are GET-only; address this before later authentication/mutating worktree flows. No authentication, product behavior, CI gate or production readiness is certified here.

## CET-003 tests and quality gate

After `setup`, `install` and `start`, run the normal pre-PR aggregate:

```powershell
.\scripts\quality.ps1
.\scripts\dev.ps1 test          # database target check + both PHP suites
.\scripts\dev.ps1 test-db-check # read-only live target safety check
```

The aggregate stops at the first failed command and returns a nonzero process result. It includes Git-visible secret/generated-file checks, Composer manifest/platform checks, PHP syntax, Pint, Larastan/PHPStan level 5, Prettier, strict TypeScript, Vitest, production build, PostgreSQL target validation, both PHP suites, and Composer/npm audits. It builds assets before HTTP smoke tests, so a fresh checkout does not need a previous build. Required checks must pass and run; an empty PHP/component suite, skipped/incomplete required PHP test or focused-only component test is a failure. No coverage threshold, deployment, browser framework or CI cache is added.

For focused checks, load only the generated project name (never print the environment's app key). These commands use this checkout's environment, project and named dependency volumes:

```powershell
$qualityProject = ((Get-Content .env.docker | Where-Object { $_ -like 'DEV_PROJECT=*' }) -split '=', 2)[1]
$qualityCompose = @('compose', '--env-file', '.env.docker', '--project-name', $qualityProject, '--file', 'compose.yaml')
docker @qualityCompose exec -T app composer format:check
docker @qualityCompose exec -T app composer analyse
docker @qualityCompose exec -T app composer lint:php
docker @qualityCompose run --rm --no-deps node npm run format:check
docker @qualityCompose run --rm --no-deps node npm run typecheck
docker @qualityCompose run --rm --no-deps node npm run test:components
docker @qualityCompose run --rm --no-deps node npm run build
# Intentional formatting fixes (review the resulting diff):
docker @qualityCompose exec -T app composer format
docker @qualityCompose run --rm --no-deps node npm run format
```

Native focused commands expose their exit status as `$LASTEXITCODE`; use the wrapper aggregate for fail-closed orchestration. Do not substitute a raw PHPUnit command with development credentials for `dev.ps1 test`.

### Test conventions and target safety

- `tests/Bootstrap`: useful database-free routing/Inertia/health checks, retained from CET-001.
- `tests/Database`: PostgreSQL foundation and later P-layer tests. Extend `Tests\Support\PostgresTestCase` for guarded application/database tests; it checks configuration before framework fixture/reset traits execute. Test targets must be `APP_ENV=testing`, `postgres:5432`, database and restricted role `cetakin_test`, in this project's development container. Cached configuration, production/development targeting and inherited database URLs are rejected. PHPUnit defaults never override an explicit production environment to make it appear safe.
- The shared guard also protects migration/reset tooling. Both database roles remain restricted by PostgreSQL itself. Only Access/session/throttle persistence is added by CET-004; no request/order/file/payment schema exists.
- The smoke suite demonstrates rollback and two actual independent PostgreSQL connections using distinct backend process identities and commit visibility. A short-lived uniquely named probe table is removed in `finally`; it does not certify locks, uniqueness or concurrent business operations. Later genuine race tests must coordinate connections explicitly, without sleeps or an enclosing transaction that hides committed work.
- Each database test gets a unique private storage root inside this worktree's volume. No real designs are used. Future S-layer tests can use this convention; no object storage adapter or empty storage suite exists yet.
- `resources/js/**/*.test.ts(x)` runs with Vitest 5, React Testing Library and jsdom. Bootstrap and Access component cases check visible content, accessible fields, errors/focus, pending controls and logout. Access cases use the real Inertia form helper and mock its public transport boundary. No snapshots or browser framework is introduced.
- Pint formats PHP; Larastan/PHPStan checks framework-aware types; Prettier formats frontend sources/configuration; TypeScript checks types and unused declarations. ESLint was not added: the current TypeScript 7 lock is outside the supported range of the evaluated typescript-eslint release, and overlapping unsupported tooling adds no reliable gate.
- All dependency versions are locked. npm installation ignores package lifecycle scripts. Composer audit fails on its default advisory threshold; npm audit fails on HIGH/CRITICAL advisories, while lower severity findings remain visible. The lightweight scanner checks Git-visible tracked/unignored files for high-signal keys and forbidden environment/generated/private data, without printing matched secrets. It is not exhaustive secret detection or a history scan.

### GitHub quality workflow

`.github/workflows/quality.yml` runs on pull requests and pushes to `main`. It checks out the event commit with an immutable official action SHA, read-only repository permissions and credential persistence disabled. The Linux runner uses the same Compose images, isolated databases, guards, locked installs and PowerShell aggregate as Windows; it uses no SQLite, production secrets or deployment step. Required failures fail the job; cleanup runs even after failure. Repository branch-protection settings must separately require the `Quality / quality` check before merge; this task does not configure remote repository policy.

### Customer session and worktree browser workflow

Existing CET-002/003 environments need no regeneration: `migrate`, `build` and `start` activate the new schema/assets/Compose settings. Use `config` to find the canonical browser URL. Open `/register`, enter Name, Email, WhatsApp/phone and matching passwords, then inspect `/account` and its own contact link. `/login` accepts email/password; the account's logout action ends the active server session. This is continuity of access, not legal identity/design-ownership verification. The authoritative policy remains PRD section 7.1. No verification email, reset link, account-edit wizard, staff grant or assisted recovery is implemented.

Use a separate `cetakin-<worktree-id>.localhost` hostname for each worktree in the **same** browser profile. `.localhost` is a [special loopback suffix](https://www.rfc-editor.org/rfc/rfc6761.html#section-6.3); [Chromium's resolver tests](https://chromium.googlesource.com/chromium/src/+/HEAD/net/dns/host_resolver_manager_unittest.cc) cover the subdomain behavior used by Edge/Chrome. No hosts-file edit or WSL is required. Do not switch account flows to the shared `localhost`/`127.0.0.1` hostname or set a shared cookie domain. Host PowerShell DNS tools may not resolve the special suffix; an explicit health probe is:

```powershell
# Replace both values with the URL printed by config; no DNS/hosts change needed.
curl.exe --noproxy '*' --resolve 'cetakin-<worktree-id>.localhost:<port>:127.0.0.1' 'http://cetakin-<worktree-id>.localhost:<port>/up'
```

This resolves the earlier CET-002/003 per-port XSRF warning through host scoping, without custom CSRF cookie names or middleware. Laravel 13 uses its [supported origin/token checks](https://laravel.com/framework/docs/13.x/csrf); Inertia sends the native `X-XSRF-TOKEN` header. Same-origin Fetch Metadata may satisfy Laravel's origin check; requests without trusted origin metadata must supply a valid token. PostgreSQL tests deliberately leave the unit-test CSRF bypass and assert missing/invalid-token rejection. Never disable CSRF to diagnose a 419; check the hostname, stale page/token and current session instead.

`SESSION_DRIVER=database` and `CACHE_STORE=database` are the application defaults and Compose overrides. Keep session cookies host-only, HttpOnly and SameSite=Lax. `SESSION_SECURE_COOKIE` defaults true in production and may be explicitly configured: local HTTP uses false, production HTTPS must use true. Do not copy the local example into production unchanged. Authentication rotates the session through installed Laravel 13's session guard; logout invalidates it and regenerates CSRF state. Current relationship checks run on every account/detail request; a previously displayed page confers no access after revocation.

To check two worktrees manually, migrate/build/start both; register distinct synthetic customers using their two canonical URLs in one profile. Alternate own-account/detail reads, logout/login in A and confirm B remains signed in, then reverse the roles. No 419 should occur with each page's own current token. Never place real customer contacts/passwords in fixtures. Focused backend/component cases and the aggregate above remain the normal automated gate.

## CET-003 validation record — 2026-10-07

The complete PowerShell gate passed on the Windows Docker Desktop environment above: PHP syntax/Pint (19 files), Larastan/PHPStan level 5, frontend formatting, strict TypeScript, one component test, production asset build, four Bootstrap tests (22 assertions), four PostgreSQL foundation tests (16 assertions), platform checks and both dependency audits (zero advisories). Production, development, inherited URL and real configuration-cache targets were rejected before fixture assertions. PostgreSQL remained 18.6; no business schema was introduced.

A temporary wrong HTTP assertion made the normal aggregate fail at PHPUnit with exit 1; exact original test bytes were restored and the full gate passed afterward. Empty PHP selection, skipped PHP test and focused-only component test also returned nonzero; temporary changes were restored. Independent review found no BLOCKER/HIGH issue and two MEDIUM runner gaps, both fixed by explicit skip/incomplete failure flags and Vitest `allowOnly: false`.

A second actual Git worktree installed both committed locks into fresh isolated volumes and passed the full gate. Resetting either test database preserved the other worktree's development/test markers and both private storage markers. Stopping the temporary project retained its five volumes and left the first application's exact health/page responses successful. Synthetic probes and all temporary-worktree resources were removed. Both PHP suites passed again after the final reset. Protected planning documents/ADRs were unchanged, and ignored environment/dependencies/assets/private files remained untracked.

Workflow YAML/CI contract and PowerShell syntax checks passed; hosted GitHub Actions and the Linux runner were not executed in this task. The workflow has no deployment or production-secret requirement. Existing Composer warnings about license metadata/exact version pins and an npm update notice remain non-blocking; no license or dependency upgrade policy was invented. The XSRF worktree warning remains the CET-004 consideration above. This verifies a foundation quality gate, not production readiness or future business/concurrency/storage behavior.

## CET-004 validation record — 2026-10-10

Validated final source on `codex/cet-004-customer-identity`, based on `43ecf80`, with the same Windows PowerShell/Docker Desktop images and unchanged dependency locks. Normal `quality.ps1` returned exit 0 in both the primary checkout and a temporary real Git worktree: PHP syntax (31 sources), Pint (32 files), Larastan/PHPStan level 5, Prettier, TypeScript, 10 component cases, production assets, five Bootstrap tests/26 assertions and 19 PostgreSQL tests/198 assertions. The PostgreSQL suite includes 15 Access cases (including three Unicode datasets) and four foundation cases. Composer/npm live audits reported zero advisories; Git-visible secret/generated-file checks passed. Initial Docker DNS/registry timeouts correctly stopped the gate; an IPv4-only audit diagnosed connectivity, and subsequent normal aggregate runs passed without any network override or relaxed check.

The Access migration succeeded on the existing CET-001–003 development database containing only migration bookkeeping, without reset, and on the second worktree's empty PostgreSQL 18.6 database. Explicit test reset remained confined to the test database, preserving development identity data and the second worktree. Tests prove atomic registration/rollback, duplicate identifiers, hashed credentials/bcrypt byte limits, server-owned relationships, pair/FK constraints and broader cardinality, generic invalid credentials, persistent login throttling/decay, session/CSRF rotation, old/deleted/expired session denial and current A/B relationship isolation with safe projections. Production-branch middleware smoke proves missing/invalid-token rejection, valid registration, Secure/HttpOnly/Lax/host-only cookie configuration; isolated configuration cases cover absent/local/production environment defaults.

One shared standard CookieJar exercised real non-test-mode HTTP across two project-specific `.localhost` hosts, using explicit loopback resolution like `curl --resolve`: synthetic registration, own account/detail reads, other-host token rejection (419), correct-token logout/login, old-session denial and preservation of the other authenticated worktree. Actual response cookies were host-only, with HttpOnly/Lax session flags. Stopping the second project preserved its five volumes and left the primary healthy. Its volumes/image tag/worktree and task-owned synthetic identities/probe files were removed afterward. This is protocol/session evidence: the built-in browser blocked both hostname and loopback URLs with `ERR_BLOCKED_BY_CLIENT`, so the manual browser checklist/visual execution is **unrun**, and no browser pass is claimed. No browser framework was added.

Independent Code Reviewer found one HIGH Unicode-normalization defect and one MEDIUM Secure-cookie fallback defect; both were fixed and re-reviewed. Registration/login now use Laravel's supported UTF-8 normalization, with real PostgreSQL registration/duplicate/login cases for Latin, dotted-I and final-sigma inputs. Stored normalized values satisfy the database's canonical CHECK; no broader Unicode case-equivalence policy is claimed. Reality Checker independently closed the normalization finding and found no tenancy/staff/recovery/email or later-feature expansion. Final integrated gates passed after the fixes. Hosted GitHub Actions/Linux execution remains **unrun**; the existing recursive PostgreSQL/component discovery includes the new tests without workflow changes. The 14 protected planning/ADR files were unchanged. Existing Composer license/exact-pin warnings and the npm update notice remain non-blocking. This completes customer-entry correctness evidence, not assisted recovery, owner pilot acceptance, or production reliance; BD-02–17 remain unapproved and CET-005/006 were not started.
