# Windows development — CET-001–003

This is local infrastructure, not a production deployment. Two long-running services are sufficient: a PHP CLI Laravel development server with Composer, and PostgreSQL. Node is a disposable build tool, not another application server. File sessions and array cache remain; no authentication, domain schema, workers, Redis, object emulator or deployment is added. CET-003 adds foundation tests and a PR quality gate.

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

`setup` creates ignored `.env.docker` with a fresh local app key, per-checkout identity and automatically chosen loopback ports. It never overwrites an environment. Existing host `.env` is preserved and not used for Compose interpolation. Compose explicitly sets the local application/database values; no production values belong in either local file. Setup reports the application port; open `http://127.0.0.1:<reported-port>/` and `/up`. Health proves routing only, not PostgreSQL readiness or production readiness.

`install` builds PHP with `pdo_pgsql`, the existing app extensions and Composer, then installs **committed locks** into isolated volumes. `build` typechecks and builds production-format assets into ignored checkout-local `public/build`. Re-run it after frontend changes. This deliberately uses compiled assets without a persistent Vite/HMR service or extra exposed port. Host HMR remains an optional CET-001 workflow, not part of this Compose topology; stop it/remove stale `public/hot` before testing compiled assets.

Only PostgreSQL migration bookkeeping is created by `migrate`; there are no business, users, sessions, jobs or cache table migrations. Identity/session persistence belongs to CET-004. `test` runs the four database-free CET-001 smoke tests and four PostgreSQL foundation tests using the designated test environment. These prove infrastructure behavior, not business invariants or concurrency races.

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

No SQLite connection is defined by this project. CET-003 adds a guarded PostgreSQL foundation suite and independent-connection smoke, without business fixtures, factories or race tests.

## Worktrees and resource ownership

The wrapper derives `cetakin-<12-character SHA-256 prefix>` from the canonical absolute checkout path (case-normalized on Windows; case-preserving on Linux CI). `.env.docker` must match that worktree. Project-scoped network, PostgreSQL, vendor, node_modules, storage and bootstrap-cache volumes have no fixed global names or `container_name`. Ports default to distinct hash-derived ranges and bind only `127.0.0.1`. Collisions fail rather than silently reusing a listener; choose explicit unused ports when needed:

```powershell
.\scripts\dev.ps1 setup -AppPort 28001 -DatabasePort 38001
```

Each separate worktree runs its own `setup/install/build/start` and has separate test credentials/targets and runtime volumes. The transport session cookie is project-named because browser cookies ignore port boundaries. Checkout-local asset output and source are never shared. Container private files live in that project's `app_storage` volume at `/workspace/storage/app/private`; they are **not** the host CET-001 `storage` directory. Do not put confidential real designs into this foundation environment. The shared Git object/history store is not runtime data.

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
- The shared guard also protects migration/reset tooling. Both database roles remain restricted by PostgreSQL itself. No business migration is added; existing migration bookkeeping is the only persistent schema.
- The smoke suite demonstrates rollback and two actual independent PostgreSQL connections using distinct backend process identities and commit visibility. A short-lived uniquely named probe table is removed in `finally`; it does not certify locks, uniqueness or concurrent business operations. Later genuine race tests must coordinate connections explicitly, without sleeps or an enclosing transaction that hides committed work.
- Each database test gets a unique private storage root inside this worktree's volume. No real designs are used. Future S-layer tests can use this convention; no object storage adapter or empty storage suite exists yet.
- `resources/js/**/*.test.ts(x)` runs with Vitest 5, React Testing Library and jsdom. The existing Bootstrap page test queries its visible heading and confirmation; only Inertia's document-head integration is mocked. The harness can test simple interactions when actual controls exist. No snapshots or browser automation are introduced.
- Pint formats PHP; Larastan/PHPStan checks framework-aware types; Prettier formats frontend sources/configuration; TypeScript checks types and unused declarations. ESLint was not added: the current TypeScript 7 lock is outside the supported range of the evaluated typescript-eslint release, and overlapping unsupported tooling adds no reliable gate.
- All dependency versions are locked. npm installation ignores package lifecycle scripts. Composer audit fails on its default advisory threshold; npm audit fails on HIGH/CRITICAL advisories, while lower severity findings remain visible. The lightweight scanner checks Git-visible tracked/unignored files for high-signal keys and forbidden environment/generated/private data, without printing matched secrets. It is not exhaustive secret detection or a history scan.

### GitHub quality workflow

`.github/workflows/quality.yml` runs on pull requests and pushes to `main`. It checks out the event commit with an immutable official action SHA, read-only repository permissions and credential persistence disabled. The Linux runner uses the same Compose images, isolated databases, guards, locked installs and PowerShell aggregate as Windows; it uses no SQLite, production secrets or deployment step. Required failures fail the job; cleanup runs even after failure. Repository branch-protection settings must separately require the `Quality / quality` check before merge; this task does not configure remote repository policy.

### Worktree browser consideration for CET-004

The LOW CET-002 `XSRF-TOKEN` warning remains tracked: browser cookies ignore ports, so different worktrees at the same localhost hostname may overwrite that framework cookie despite project-specific session cookies. Current application routes are GET-only. Before CET-004 introduces authentication or mutations, validate an appropriate development hostname/browser-profile isolation approach; CET-003 does not choose future authentication/session policy.

## CET-003 validation record — 2026-10-07

The complete PowerShell gate passed on the Windows Docker Desktop environment above: PHP syntax/Pint (19 files), Larastan/PHPStan level 5, frontend formatting, strict TypeScript, one component test, production asset build, four Bootstrap tests (22 assertions), four PostgreSQL foundation tests (16 assertions), platform checks and both dependency audits (zero advisories). Production, development, inherited URL and real configuration-cache targets were rejected before fixture assertions. PostgreSQL remained 18.6; no business schema was introduced.

A temporary wrong HTTP assertion made the normal aggregate fail at PHPUnit with exit 1; exact original test bytes were restored and the full gate passed afterward. Empty PHP selection, skipped PHP test and focused-only component test also returned nonzero; temporary changes were restored. Independent review found no BLOCKER/HIGH issue and two MEDIUM runner gaps, both fixed by explicit skip/incomplete failure flags and Vitest `allowOnly: false`.

A second actual Git worktree installed both committed locks into fresh isolated volumes and passed the full gate. Resetting either test database preserved the other worktree's development/test markers and both private storage markers. Stopping the temporary project retained its five volumes and left the first application's exact health/page responses successful. Synthetic probes and all temporary-worktree resources were removed. Both PHP suites passed again after the final reset. Protected planning documents/ADRs were unchanged, and ignored environment/dependencies/assets/private files remained untracked.

Workflow YAML/CI contract and PowerShell syntax checks passed; hosted GitHub Actions and the Linux runner were not executed in this task. The workflow has no deployment or production-secret requirement. Existing Composer warnings about license metadata/exact version pins and an npm update notice remain non-blocking; no license or dependency upgrade policy was invented. The XSRF worktree warning remains the CET-004 consideration above. This verifies a foundation quality gate, not production readiness or future business/concurrency/storage behavior.
