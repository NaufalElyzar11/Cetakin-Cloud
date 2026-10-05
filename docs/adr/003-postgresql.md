# ADR-003: PostgreSQL and guarded relational actions

- Status: Proposed
- Date: 2026-10-05
- Related: ARCHITECTURE sections 4, 5, 11, 13

## Context

Agreement versions, exact file provenance, one initial order, retained money and start-versus-amendment conflicts require reliable relational consistency.

## Decision

Recommend PostgreSQL with Eloquent, explicit migrations, short transactions, common Request/Order coordination locks and integrity constraints. Confirmed outcomes/history commit together; scoped operation identities make consequential retries safe. Test these behaviors using PostgreSQL.

## Alternatives

MySQL is credible but offers no demonstrated reason to support another engine. SQLite-only tests cannot certify PostgreSQL lock/constraint behavior. Separate stores or eventual agreement-to-order handoffs weaken required consistency.

## Consequences

Constraint/locking design and migration compatibility require care; application validation alone is insufficient. Exact money precision and payment policy remain BD-07/14. No schema or migrations are implemented.

[PostgreSQL row locks](https://www.postgresql.org/docs/current/explicit-locking.html#LOCKING-ROWS), [Laravel transactions](https://laravel.com/framework/docs/database#database-transactions).

