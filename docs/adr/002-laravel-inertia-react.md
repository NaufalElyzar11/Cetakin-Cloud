# ADR-002: Same-origin Laravel, Inertia and React

- Status: Proposed
- Date: 2026-10-05
- Related: ARCHITECTURE sections 2, 7, 8, 10

## Context

The MVP needs private interactive forms and status work, with no independent API/mobile consumer or storefront SEO requirement.

## Decision

Recommend Laravel/PHP with React/TypeScript through Inertia and Vite, one origin/release and no SSR initially. Node builds assets; PHP handles production requests. Choose supported compatible versions when implementation is authorized.

## Alternatives

Laravel REST plus Next.js separates contracts/auth/routing/releases; SSR adds runtime work, while static export has different constraints. Blade/Livewire is equally business-capable and may reduce JavaScript maintenance.

## Consequences

Server business actions and explicit projections support current UX without a second application. Adoption is conditional on real React/TypeScript maintenance capability and useful reusable interactive components/tests; portfolio practice alone is insufficient. If that capacity is absent, reconsider Blade/Livewire. Inertia responses are not a public API; a future Flutter client still needs deliberate contracts and identity work. Do not import starter-kit registration/email/SSO features before BD-01 approval.

[Inertia server setup](https://inertiajs.com/docs/v3/installation/server-side-setup), [optional SSR](https://inertiajs.com/docs/v3/advanced/server-side-rendering).
