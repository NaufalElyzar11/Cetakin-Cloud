# ADR-005: Server sessions and distinct business authorization

- Status: Proposed
- Date: 2026-10-05
- Related: ARCHITECTURE sections 7, 8, 16; BD-01/05

## Context

Same-origin customer/staff work needs attributable decisions, current ownership/task access and revocation without assuming a full identity product.

## Decision

Recommend database-backed server sessions for an approved verified actor relationship, secure cookie/HTTPS/CSRF controls and Laravel policies. Check current ownership/grants per action; explicitly project customer/operator data.

## Alternatives

Registered credentials and verified limited private access can both yield sessions; BD-01 chooses verification, attribution, expiry/provisioning and recovery obligations. No entry/email/reset workflow is assumed. JWT/OAuth/SSO adds work without an approved external consumer; a real mobile/API client later needs a deliberate token/credential and revocation contract. File sessions complicate shared deployments; Redis adds another service solely for login.

## Consequences

A session proves authenticated continuity, not design rights, customer ownership or administrator authority. Admin/customer role overlap never permits impersonation. BD-01 determines verification/contact/account needs before implementation; no registration/reset/email feature is approved. Revocation denies later requests but cannot erase information already received.

[Laravel sessions](https://laravel.com/framework/docs/session), [authorization](https://laravel.com/framework/docs/authorization).
