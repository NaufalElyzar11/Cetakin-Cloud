# ADR-007: Provider-neutral managed deployment pattern

- Status: Proposed
- Date: 2026-10-05
- Related: ARCHITECTURE sections 14, 15, 17; BD-06/16

## Context

Cetakin needs affordable reliable operation without a dedicated infrastructure team. Nominal server price excludes patching, backups and incident labor.

## Decision

Recommend one managed PaaS Laravel application with compiled assets, managed PostgreSQL and private object storage, subject to verified budget, region, durability, recovery and access capabilities. No vendor or exact price is selected; Node is build tooling with SSR disabled.

## Alternatives

A Docker VPS is viable with a named capable maintainer and off-host recovery. Split Laravel/Next hosting adds coordinated releases without current product benefit.

## Consequences

Managed service fees may be higher; verify backup/export/restore and rollback rather than assuming them. Joint recovery preserves database/file identities, money/history and latest deletion/revocation evidence. Periodic backups alone cannot promise no acknowledged loss; inadequate capabilities block reliance. Releases need quality gates, compatible migrations and owner-authorized deployment. Provider selection requires further review, not automatic provisioning.

