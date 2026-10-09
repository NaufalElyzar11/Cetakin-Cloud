# ADR-005: Server sessions and distinct business authorization

- Status: Accepted at G0; BD-01 v1 policy alignment approved 2026-10-10
- Date: 2026-10-05
- Related: ARCHITECTURE sections 7, 8, 16; BD-01/05

## Context

Same-origin customer/staff work needs attributable decisions, current ownership/task access and revocation without assuming a full identity product.

## Decision

Use the accepted database-backed Laravel server sessions, secure cookie/HTTPS/CSRF controls and policies. Check current customer relationships/staff grants per action; explicitly project customer/operator data. BD-01 v1 (PRD section 7.1) now selects email/password self-registration, supported password hashing, rate-limited login and session rotation at authentication. No JWT, OAuth or SSO.

## Alternatives

Before BD-01 adoption, registered credentials and verified limited private access were alternatives. BD-01 v1 selects registered credentials; limited-private-access entry is not the current MVP policy. Email verification is not required for the first MVP; no email-delivery or self-service email password-reset workflow is included. Owner/admin provides manual assisted recovery following verification outside the system, with attributable access changes and authority gated by BD-05. JWT/OAuth/SSO adds work without an approved external consumer; a real mobile/API client later needs a deliberate token/credential and revocation contract. File sessions complicate shared deployments; Redis adds another service solely for login.

## Consequences

A session proves authenticated continuity, not legal identity, verified email ownership, design rights, customer ownership or administrator authority. Initial registration creates one User, one Customer profile (Name, Email, WhatsApp / phone number) and one relationship, without a permanent one-to-one model. Email is the login identifier; current explicit relationships authorize customer access and quotation acceptance. Admin/customer role overlap never permits impersonation, and registration grants no staff role. Logout/expiry/revocation deny later requests but cannot erase information already received. Recovery preserves original decision attribution; staff authority remains BD-05. This policy update starts no CET-004 implementation.

[Laravel sessions](https://laravel.com/framework/docs/session), [authorization](https://laravel.com/framework/docs/authorization).
