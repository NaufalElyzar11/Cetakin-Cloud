# ADR-001: Modular monolith

- Status: Proposed
- Date: 2026-10-05
- Related: ARCHITECTURE sections 1, 3, 4; DOMAIN consistency boundaries

## Context

Cetakin has one small custom-print business workflow. Exact agreement/order/job/payment outcomes need coordination; separate deployment or scaling requirements are unproven.

## Decision

Recommend one application and authoritative relational database, organized into Access, Intake & Quotations, Operations, Payments and File Handling responsibilities. Named workflow actions coordinate guarded cross-module outcomes. Modules own writes; no generic framework or service per entity.

## Alternatives

Microservices introduce network/failure/release coordination without current benefit. An unstructured monolith reduces initial organization but weakens ownership of security and consequential writes.

## Consequences

One release and ordinary transactions simplify operations. Internal boundaries require disciplined APIs, review and focused tests; they are not automatically enforced by folders. Independent deployment is deferred until evidence justifies its cost. This proposal does not authorize implementation or approve business policies.

