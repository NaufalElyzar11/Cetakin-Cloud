# ADR-004: Private design objects and exact versions

- Status: Proposed
- Date: 2026-10-05
- Related: ARCHITECTURE sections 6, 16, 17; BD-03/04/06/16

## Context

Customer designs are sensitive; file replacement, denied access, permitted deletion and recoverable exact agreement references matter.

## Decision

Recommend managed private production object storage with a verified S3-compatible interface through Laravel's filesystem adapter, with immutable generated object/version identities and SQL provenance/availability. Development may use private local disk. Default downloads freshly authorize and stream through the application; no public object links.

## Alternatives

Production local disk ties durability to host maintenance. Self-hosted S3-compatible storage retains patch/capacity/recovery burden despite the interface. Managed storage adds cost and requires capability verification. Presigned download URLs leave bearer access until expiry after revocation; public buckets are unsuitable.

## Consequences

Object writes and SQL commits are separate failure boundaries. Reserve a durable scoped upload intent before external write; require tested write-once creation/exact immutable provider version and matching size/digest on reuse. Association and cleanup coordinate through the same intent guard/claim: never delete in-flight, uncertain or confirmed content as detached; cleanup claim forbids subsequent association. Keep external I/O outside SQL locks and confirm actual deletion. This is technical coordination, not saved drafts. Already downloaded bytes cannot be recalled. Provider capabilities, limits, rights, retention/recovery copies and demonstrated durability remain unresolved gates.

[Laravel storage](https://laravel.com/framework/docs/filesystem), [OWASP upload guidance](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html).

[AWS conditional-write capability example](https://docs.aws.amazon.com/AmazonS3/latest/userguide/conditional-writes.html); verify the chosen compatible provider rather than assuming support.
