# ADR-006: No initial Redis or background workers

- Status: Proposed
- Date: 2026-10-05
- Related: ARCHITECTURE sections 9, 11, 12

## Context

Production queue currently means a manual business work list. No required automated notifications, slicing or other asynchronous product work justifies worker operations.

## Decision

Recommend synchronous short guarded business actions and no initial Redis, cache cluster or worker. If an approved measured task requires durable background processing later, evaluate Laravel's database queue first; Redis requires a concrete throughput/cache need.

## Alternatives

An initial database worker adds deployment/retry/failure ownership without a task. Redis adds another service and monitoring/persistence concerns. A queue cannot replace atomic agreement/order or payment outcomes.

## Consequences

Controlled reconciliation/backups remain operational procedures. Future jobs need bounded idempotent retries, after-commit dispatch and failed-job ownership; these are conditions for introducing workers, not implemented features. Database queue load may eventually justify another backend.

[Laravel queue backends](https://laravel.com/framework/docs/queues).

