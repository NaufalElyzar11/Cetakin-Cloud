# Cetakin Cloud Test Strategy

**Status: Proposed for human review.** This strategy follows `AGENTS.md`, `README.md`, `VISION.md`, the reduced `PRD.md`, `DOMAIN.md`, `ARCHITECTURE.md` and ADR-001–007. Product requirements remain authoritative; architecture/ADRs remain proposed. No application, tests, CI workflows or dependencies are created, and no software correctness or readiness is certified here.

## 1. Principles and policy gates

- Prove observable business outcomes, permissions and preserved evidence, not class names, ORM calls or private methods.
- Every implemented feature needs meaningful success, rejection and failure cases. Authorization denial is a first-class outcome: no confidential disclosure or unauthorized mutation.
- Database-backed evidence is required for important relationships, agreement/order creation, retained payments and production guards. PostgreSQL locking, constraints and races require PostgreSQL; SQLite cannot certify them.
- Frontend checks prove visible behavior and safe projections. Hiding a button never proves server authorization.
- Use the lowest sufficient layer; reuse representative fixtures and table-driven cases. Do not chase 100% coverage, every state pair, load testing or a large browser suite.
- Required failed, skipped or unrun tests prevent completion. Name the missing evidence; do not relabel an incomplete feature as done.

BD-01 v1 is approved (2026-10-10; PRD section 7.1); BD-02/03 still gate private intake. BD-05 precedes first staff actions/review and administrative assisted recovery; BD-04/06 precede real designs. BD-07–14 gate their quotation, change, release, retry, outcome and payment behaviors. Approved policies supply test oracles; unresolved policy-dependent features cannot pass their acceptance gate using invented defaults. Synthetic exploratory examples may proceed after implementation authorization, clearly marked provisional. BD-16 gates reliance; BD-17 gates pilot/value claims. BD-15 automation stays deferred. Record adopted policy/version beside the dependent acceptance examples.

## 2. Layers and responsibilities

| Layer | Evidence and limits |
| --- | --- |
| Unit/domain (U) | Money arithmetic under adopted precision, positive whole requested quantity versus zero usable output, transition guards, derived payment summary and truthful public status. No database mocks pretending to prove persistence. |
| Application/HTTP feature (F) | Real application actions, PostgreSQL persistence, policies and explicit Inertia projections: intake/review/clarification, quote decisions, jobs/reprints, payments and outcomes. Assert stored outcome/history as well as response. Most workflow coverage lives here. |
| PostgreSQL integration (P) | Constraints, rollback, migrations, coordinated independent-connection races, operation identity and coherent agreement/payment views. Narrow cases target invariants ordinary sequential feature tests cannot establish. |
| File integration (S) | Private local adapter for normal flow; real compatible object adapter for non-overwrite/exact-version behavior, integrity, download restrictions and cleanup reconciliation. Fault injection supplements—not replaces—adapter evidence. |
| Frontend/component (C) | User interactions, errors, pending/progress versus confirmed results, stale/unknown responses, restricted presentation and keyboard/focus behavior. Server remains authoritative. |
| Browser (E) | Two selected cross-role journeys across real browser/server/database/storage boundaries; see section 10. No browser copy of every domain guard. |
| Operational/manual (O) | Joint restore, safe release smoke/rollback compatibility, business workflow fit, ordinary responsiveness and fallback rehearsal. These do not replace automated correctness checks. |

Prefer existing framework test facilities after stack approval. Tool/runner selection and configuration belong to implementation; introducing a separate automation platform is unnecessary. Test expected public behavior rather than framework internals.

Focused C cases use semantic labels/roles: enter approved values, receive server field/error-summary feedback, keep recoverable values in page memory, distinguish 100% upload transfer from confirmed request/file association, and handle loading/failure/unknown outcome without optimistic acceptance/payment/stop/closure. Display exact quote version/files/terms/total; stale conflicts refresh without auto-accepting another version. Check physical/payment separation, defect/dispute warnings, role-specific actions, keyboard/error focus/status announcements and ordinary responsive layouts. Avoid whole-tree snapshots, private method assertions or a large visual/device matrix. Check sensitive props and refreshed responses on the server, plus no private localStorage/query-string persistence and deliberate private/no-store response behavior.

## 3. MUST requirement traceability

These grouped targets account for all **37 active MUST IDs**. Layers name primary evidence, not a requirement for duplicate tests at every layer. Workflow scenarios can prove several IDs together; the implementing change records actual test references/results.

| Capability / PRD journeys | MUST IDs | Essential evidence / layers |
| --- | --- | --- |
| Intake / J01 | REQ-001, REQ-002 | Valid owned request/exact inputs; invalid input or failed upload no success; same attempt one result, deliberate new request allowed. F, S, P; E-01 |
| Review, clarification, decline and work list / J02–04 | REQ-004, REQ-005, REQ-006, REQ-007 | Insufficient review blocks quote; linked question/reply/version/re-review; silence not agreement; reasoned terminal decline; waiting work retains action/responsibility. F, C |
| Quotation / J05–08 | REQ-008, REQ-009, REQ-010, REQ-011, REQ-012 | Exact identified fixed proposal; one eligible version; ownership/deadline/decision evidence; stale/rejected version denied and retry recovers result. F, P, C; E-01 |
| Initial order / J09 | REQ-013 | Acceptance, converted request, exact agreement/files/payable and one retrievable order commit together; no successful orphan/duplicate. F, P; E-01 |
| Supported change / J06–08 | REQ-014 | Never-ever-started queued amendment updates same order/current payable; holds/obsolete jobs; rejected/unanswered change preserves original; late proposal does not authorize changed work. F, P, C |
| Production, failure/reprint, tracking / J10–12 | REQ-016, REQ-017, REQ-018, REQ-019, REQ-020, REQ-021, REQ-022 | Approved release/authority; actual start/output/stop; failed attempt retained/new linked approved job; usable quantity/readiness/defect holds; private truthful public progress. U, F, P, C; E-02 |
| Fulfillment, cancellation, closure/correction / J14–16 | REQ-023, REQ-024, REQ-025, REQ-026 | Verified terminal output; request differs from approval/stop; residual money responsibility; invalid transitions unchanged; visible factual error/affected hold/retained correction without reopening. F, P, C; E-02 |
| Files | REQ-027, REQ-028, REQ-030, REQ-031 | Bounded supported upload; exact owned provenance; immutable replacement references; unavailable-file hold; adopted assisted deletion truth/evidence. S, F, P |
| Access and important history | REQ-033, REQ-034, REQ-035 | Small allowed/denied matrix, direct requests and projections; current grants/revocation, role overlap, retained attributable changes and safe denial evidence. F, S, C, P |
| External payment recording / J13 | REQ-036, REQ-037, REQ-038, REQ-039 | Verified retained events/corrections, derived approved summary, same-event retry versus legitimate equal receipts, explicit unknown/excess/dispute. U, F, P, C |
| Cross-cutting integrity/recovery | REQ-041, REQ-043 | Conflicts/failures preserve confirmed outcomes/provenance; representative linked restoration including permissions/files and recent changes; safe fallback. P, S, O |

Moved REQ-042/047/050 are addressed by sections 4/15/14, with REQ-041/048 harmful-input/concurrency detail here. MERGE IDs are not extra requirements. REQ-045/046 numerical performance/SLA targets remain deferred; REQ-049 instrumentation is optional and manual evidence suffices. No scope returns through a test requirement.

## 4. Focused authorization matrix

Use Customer A, Customer B, one Administrator with approved task access, and one Operator assigned A's production work but not B's. Each customer has comparable records/files so absent fixtures cannot explain denial. For symmetric ownership paths, swap A/B through shared parameterized cases rather than duplicate suites.

| Boundary | Customer A / B | Administrator | Operator only |
| --- | --- | --- | --- |
| Lists/detail/public status | Own records/public fields allowed; other's identifiers/list entries denied/absent | Necessary authorized business records allowed | Assigned operational list/detail only; B/customer directory denied |
| File metadata/download/replacement/deletion | Own permitted read/replacement or deletion request allowed; B direct/nested IDs denied | Task-authorized file work/approved deletion only; ungranted sensitive design denied | Assigned necessary design allowed; B or unassigned design/change/deletion denied |
| Quote decisions | Own eligible accept/reject allowed; other's denied | Issue/replace with guards allowed; accepting for A denied | No quote-price projection or decision/issue authority |
| Production | Read own public status; mutation denied | Create/assign/approve permitted guarded work | Authorized actual updates allowed; job creation/reprint approval/unauthorized job denied |
| Payment | Own safe summary; full evidence/recording denied | Verified event/correction allowed under policy | Prices/payments/summary/internal financial references absent; direct reads/writes denied |
| Cancellation/closure | Own request allowed; approval/fulfill/close denied | Guarded approve/decline, fulfill/close allowed | Factual stop allowed; order cancellation approval/fulfill/close denied |
| Grants/revocation | No self-promotion | Approved grant/revoke with attribution | No privilege grant; revoked task/session relationships deny later reads/downloads/commands |

Test list, detail, file and mutation boundaries via actual HTTP requests, including forged nested identifiers, payload ownership/grant fields and Inertia page props—not merely UI visibility. Assert denied requests leave business data/history unchanged apart from permitted denial evidence. Response/status conventions follow approved disclosure rules; errors disclose no private record existence/details, filenames or object paths.

Add one role-overlap case: Administrator who is also Customer A can decide their own quote through verified customer ownership, but staff privilege cannot accept B's. Remove task permission after a page loads while keeping its session, then retry update and download; both deny with current authority and revocation attribution remains. Test fresh requests after revocation and session expiry/logout. Already received bytes cannot be recalled; no test claims otherwise.

Normal framework test mode can bypass CSRF middleware; a green ordinary feature suite is not CSRF evidence. Run one focused security smoke against production-equivalent middleware/session configuration, proving valid state change and configured cross-site/untrusted-origin rejection, secure cookie settings and logout invalidation. Exercise invalid/missing token where required, without trusted same-origin metadata that legitimately satisfies the adopted protection. Reuse the testing server/browser setup without another full journey. Verify the chosen framework version's harness and origin/token rules during implementation. [Laravel CSRF testing behavior](https://laravel.com/framework/docs/13.x/csrf).

### 4.1 BD-01 v1 identity acceptance evidence

These are future feature acceptance oracles, not tests implemented by this policy update. Use actual customer entry/access endpoints in CET-004; add privileged recovery only with approved BD-05 authority in CET-005. Quotation/file checks belong to the later real workflows.

| Capability | Minimum evidence / layer |
| --- | --- |
| Initial registration | One successful self-registration creates one User, one Customer contact/profile and one current relationship. Required Name/Email/WhatsApp-or-phone contact is represented; failure cannot leave reported partial success. Email is the login identifier; no email verification or email-delivery requirement. F/P/C |
| Ownership/cardinality | A sees only customers permitted by current relationships; B's identifiers, nested routes and forged ownership/staff fields fail. Account/email matching is not authority. A controlled relationship fixture proves the model does not enforce permanent User=Customer cardinality; no multi-customer management UI is required. F/P |
| Credential/session controls | Password is not stored as plaintext and verifies through supported hashing; valid/invalid login and adopted rate-limit boundaries; authentication rotates the session; logout/expiry/revocation deny subsequent requests from an old page. Customer entry grants no staff role. F/P and focused production-equivalent session/CSRF security smoke |
| Browser behavior | Registration/login visible labels, validation/rejection/loading errors and expired-session handling are testable without an E2E suite expansion. No verification-email or reset-email flow is exposed. C |
| Manual assisted recovery | After BD-05, authorized owner/admin records an access change after external verification; customer/operator/unapproved staff cannot recover another person's access. Actor/time/reason and prior agreement attribution are preserved; later requests obey the resulting current relationship/session state. F/P/C plus O owner validation of the external process |
| Later quotation decisions | Authenticated current customer relationship permits own eligible acceptance; staff-only authority and another customer's actor cannot substitute. Original actor/version/decision/time survive assisted recovery. F/P with CET-013, not fake quotation routes in CET-004 |

Numeric throttle/session settings and credential-input handling are adopted during implementation and tested at their boundaries; this plan invents no fixed limit or new business gate.

## 5. State-machine evidence

Use table-driven feature tests for the listed transitions, with focused unit guards where independent of persistence. Each successful row proves actor/time/reason where applicable and preserved prior evidence. Rejection proves no business transition, agreement/event creation or silent history rewrite. For offered quote deadlines, freeze time just before, at and after the stated boundary; reached deadlines deny acceptance even without a scheduler/status refresh. Do not invent duration or enumerate every Cartesian state pair.

| Model | Valid transitions to cover | Representative invalid guards |
| --- | --- | --- |
| Print Request | Customer new→submitted; Administrator submitted→awaiting clarification, reviewed reply→submitted, submitted→quoted; quoted→submitted/clarification after pending quote invalidation; open→declined/cancelled under policy; initial acceptance quoted→converted | Invalid input/unfinished file; quote without review; response alone bypassing staff review; declined/cancelled/converted intake reopening or conversion; silence as acceptance |
| Quotation Version | Administrator draft→issued, issued→superseded/withdrawn; own customer eligible issued→accepted/rejected; stated deadline denies acceptance/permits expired recording | Issued payload edit; another owner; stale/current-basis mismatch; rejected/expired/withdrawn/superseded acceptance; terminal reactivation; no invented deadline |
| Order | Initial acceptance→queued; released actual start→in production; owner verifies usable output/terminal jobs→ready; approved actual same-scope ready rework→production; owner ready→fulfilled; approved stopped queued/production/ready→cancelled; accurate fulfilled/cancelled→closed | Payment/acceptance implies release; hold/unknown files; insufficient output/nonterminal jobs; known ready defect still fulfillable; cancellation request fabricates stop; closure conceals money/error; terminal restart |
| Print Job | Administrator creates queued exact work; authorized staff actual start→in progress; actual result→succeeded/failed; authorized queued cancellation; started cancellation after actual stop | Operator creates/approves retry; stale basis/hold/terminal order start; unconfirmed stop; failed units as usable; failed/succeeded/cancelled attempt reset instead of new linked retry |

A hold/delay/payment/dispute is not another production lifecycle. A distinct pause/resume state is not required in the baseline; if the PRD SHOULD behavior is later adopted, test factual same-attempt interruption/resumption, applicable permissions/release guards and truthful status. Interruption facts remain mandatory; generic reopening stays deferred. Explicitly retain first actual start after later failure/cancellation; it still blocks amendment. After readiness is questioned, public status immediately becomes uncertain/not-ready and fulfillment is blocked while historical ready evidence remains. Approved same-scope rework is not authority for changed scope/price.

## 6. Repeatability, concurrency and isolation

Use committed synthetic setup and **separate PostgreSQL connections/processes**. A single outer test transaction or sequential calls cannot demonstrate competing locks. Coordinate contenders with barriers/test hooks at the actual action boundary, verify the contender reached its intended coordination point or blocking state, deterministically exercise meaningful winner orders, and use bounded completion/timeouts; arbitrary sleeps are not proof. Assert final database/history/projection outcome after both contenders finish. Cleanup isolated databases/objects only after all participants terminate.

| Operation | Repeat / race oracle | Protection to verify |
| --- | --- | --- |
| Request submission | Same attempt/payload returns one request; changed payload under same identity conflicts; deliberate distinct attempt allowed. Two same-attempt submissions cannot produce two requests | Scoped unique operation/result plus atomic request/version association; intent guards |
| File completion/writes/cleanup | Same content resolves exact same version; changed bytes cannot overwrite/reuse it. Concurrent write/complete yields one verified association. Cleanup versus association produces associated protected content or claimed-unused deletion; never success pointing to deleted bytes | Unique scoped intent/version identity; real conditional creation or immutable provider version; common intent claim/lock; SQL history/result commit |
| Initial quote acceptance/order creation | Double acceptance gives same agreement/order; order creation is not independently repeatable unlinked work. Acceptance versus supersede/withdraw/deadline never accepts an ineligible basis | Request coordination lock, eligibility guards, one initial order uniqueness, committed result/history |
| Amendment issue versus job start | Before amendment issue, either legal winner: authorized actual start wins and freezes original basis/denies issue, or issue with blocking hold wins and denies start. Same order only; no historical-start reset | Common Order lock, Request/current-basis guards, retained start fact, exact job basis/hold |
| Amendment acceptance versus job start | Issued amendment already has a blocking hold: racing start is denied whether acceptance commits or not. Accepted change updates same order/payable, and obsolete queued jobs remain blocked until cancelled/recreated and approved release; no authorized-start-wins fixture that bypasses the hold | Common Order lock, current-basis/hold guards and retained agreement/job history |
| Payment recording | Same identity/event adds one receipt; different identity/equal legitimate receipt adds another; changed payload conflicts. Concurrent recordings conserve events/balance | Scoped operation uniqueness, retained event provenance, Order/Payment Record coordination |
| Payment versus amendment/closure | Read/write cannot mix new agreed scope with old payable; closure cannot succeed against stale unresolved money or affected error | Shared Order/current-basis coordination and coherent read snapshot; policy guards |

Also test representative cancellation/start contention: approved final cancellation cannot leave startable jobs; actual active work cannot be declared stopped. Direct constraint tests attempt duplicate initial order and invalid foreign provenance, invalid quantity, immutable issued payload/accepted evidence and destructive original-payment changes. Assert expected constraint rejection without relying solely on prevalidated UI inputs. No blanket high-load stress or distributed fault suite.

## 7. File security and storage evidence

Build small synthetic fixtures, including one accepted STL and one bounded valid 3MF under adopted BD-03 checks. Format acceptance proves supported handling, not printability or malware freedom.

| Input/access case | Expected product outcome |
| --- | --- |
| Allowed STL/3MF | Private verified completion; exact customer/request/version identity; authorized attachment download |
| Unsupported extension, renamed content/type mismatch | Safe identified rejection; extension/header alone never establishes supported content; no usable association |
| Oversize, truncated/incomplete transfer | Boundaries immediately below/at/above adopted limits; no false success; retry can resolve same attempt |
| Malformed 3MF/archive abuse | Reject corrupt container, unsafe traversal/absolute paths, excessive entries/expansion/recursion or processing bound violations; no extraction outside controlled scope |
| Unsafe XML/external entities | Reject/disable dangerous expansion and external resources; test fixture cannot cause network/local-secret reads; no uploaded code execution |
| Ownership/revocation | A cannot retrieve B metadata/bytes via direct IDs/key manipulation; operator only authorized designs; later revoked download denied even from old page |
| Availability/deletion | Missing object yields warning/affected-action hold, not substitute design; adopted deletion failure remains pending/unavailable rather than reported completed |

For production-compatible adapter evidence, verify anonymous/public URL access fails, replacement has another immutable identity, same-attempt racing writes cannot overwrite and downloaded size/digest matches recorded version. ETag alone is not the oracle. Test SQL metadata and real adapter together for association/cleanup cases; mocked storage cannot certify conditional-write/private-bucket behavior. Use an isolated compatible environment without production credentials; a provider-specific pre-reliance capability check remains necessary if emulator behavior differs. No broad age-only orphan deletion.

## 8. External-money tests

Owner-approved BD-07/09/12/14 examples define currency, precision, event arithmetic, evidence and release/correction rules. Test derived summaries from retained events, never a manual paid toggle or gateway. Parameterize adopted payable/amounts instead of treating example currency or refund entitlement as fact.

- No receipts with positive known payable is unpaid; partial versus exact receipts produce truthful balance; zero payable means no payment due without an invented receipt.
- Excess stays explicit/unresolved, without automatic refund or silent paid certainty. Unknown payable/amount is not zero; mismatched/unknown currency is unresolved without conversion.
- Approved recorded external refund/correction recalculates under adopted arithmetic, links original/reason/actor/time and preserves original order/customer provenance. Unapproved correction/arbitrary payable override/destructive reassignment is denied.
- Same-event retry adds no money; two legitimate equal payments remain distinct. Loss of response retrieves original evidence, not another receipt.
- Dispute context/owner/action remains alongside numeric balance, including an exactly paid disputed amount; resolution retains evidence.
- Recording after fulfillment/closure does not reopen physical work. Fulfillment/cancellation does not manufacture settlement; closure follows adopted residual-responsibility rule.

Test customer/operator inability to verify payments separately from their permitted public views. No payment gateway, tax, invoicing, reconciliation engine or currency-conversion tests.

## 9. Failure and recovery

Inject failures at supported boundaries; compare against the committed pre-operation baseline from a new connection and the next authorized retry, not only an exception message. Existing requests, files, agreements, orders and payment history remain intact; absence assertions concern only the failed operation's new mutations.

| Failure | Required truthful result |
| --- | --- |
| SQL transaction fails | No partial new acceptance/order/payable/history or money event; prior confirmed records unchanged. No success; safe retry retains operation identity |
| Object write succeeds, SQL association commit fails | Durable intent protects exact object; request/file not falsely confirmed; reconcile same intent then associate once or adopted unused cleanup |
| SQL intent exists, object write fails | No new submitted request/usable version or replacement success; existing request, files, agreements/history unchanged. Failure tracked; same-attempt retry cannot fabricate availability |
| Ambiguous response / acceptance response lost after commit | Display uncertainty; authorized lookup/retry resolves committed same request/order/event. New operation identity is not automatic retry |
| Late object absence or permitted deletion | Exact reference/evidence retained, unavailable warning and dependent quote/start/fulfillment hold; recovery or supported agreement only |
| Revoked actor keeps old page | New server commands/downloads denied, no stale cached permission bypass or sensitive refreshed props; UI cannot claim success |
| Deletion/cleanup fails or races completion | Claimed deletion blocks association; confirmed/in-flight/uncertain content not detached. Actual failure remains tracked until verified outcome |

Before real reliance, restore an isolated linked sample request, accepted version/order, exact object bytes, jobs, money/history and permission changes including recent confirmed outcomes. Reconcile deletion/revocation evidence before enabling access; an old backup cannot restore obsolete grants or forbidden content into use. Verify selected persistence/recovery capabilities against no acknowledged-loss guarantee, not merely an old snapshot. Rehearse fallback and record recovery owner/results under BD-06/16. No invented acceptable loss, restoration SLA or monthly uptime target. Exercise ordinary task responsiveness on agreed realistic profile before pilot; no enterprise load benchmark.

## 10. Minimal browser suite

Two full-browser flows are the starting cap, not a reason to omit important lower-layer evidence. Each uses isolated synthetic records and separate customer/staff sessions, real backend/storage and visible outcome assertions.

1. **E2E-01:** Customer submits allowed file/specification → Administrator reviews/issues identified quote → Customer accepts exact version → both retrieve same linked order, Customer sees own public status. This proves the highest-value integration and upload/decision presentation.
2. **E2E-02:** From a prepared accepted/released order, authorized Operator starts/fails work → Administrator approves linked same-scope retry → actual success/readiness → Administrator fulfills → Customer sees truthful physical outcome and separate payment summary. Prepare fixtures through controlled setup, without replaying E2E-01.

Challenge the other proposed browser flows: partial/final payment arithmetic belongs U/F/P with a targeted summary component test; cross-customer attacks belong direct HTTP/storage authorization tests; cancellation with queued/active jobs belongs F/P with focused confirmation/status component checks. They need full browser automation only if an observed integration defect justifies added cost. Manual owner acceptance still covers them. Put stale quote, revoked page and ambiguous response UI in C plus server tests; one targeted browser interaction is appropriate only when component evidence cannot exercise the actual integration. Do not silently inflate the full-flow count.

## 11. Test data and isolation

Deterministic factories/builders create minimal owned requests, incomplete/reviewed clarification, current/superseded/accepted proposals, never-started/started/failed/cancelled jobs, readiness defect, fulfilled/closed orders and retained money/disputes. Freeze clocks for deadlines; explicit operation identities distinguish retry from new action. No database identifier/order/random timing assumptions.

Generate synthetic STL/3MF and bounded malicious fixtures; no confidential Cetakin/customer files, contacts, credentials or production dumps in repository/CI/screenshots. Customer A/B are distinct business references, not an assumed one-account-one-customer policy. Store declared expected provenance/basis with fixtures; parameterize policy-dependent values only after approval.

Use explicit isolated PostgreSQL test databases, unique worktree/run/worker storage roots or bucket prefixes and unique runtime resources. Independent-connection tests use committed setup; ordinary tests may use rollback isolation where appropriate. Confirm test target before reset, forbid production connection/credentials, never reset another worktree. Bound fixture resources and clean only positively owned test data after processes finish.

## 12. CI execution plan

Design groups now; workflow files later. Run independent groups concurrently where useful, with locked dependencies/cached downloads, bounded races and no real customer data. Avoid a large browser matrix or redundant database engines.

| Group | Proposed execution gate |
| --- | --- |
| Fast PR | Format/lint, backend static analysis/frontend type checks, U and normal F, focused C; production asset build and approved dependency/security/secret checks |
| Database/storage | Real PostgreSQL migrations/constraints, rollback, P races/idempotency, S adapter/integrity/security checks; required on relevant backend/file/schema/action changes, never omitted for affected invariants |
| Browser/security | E2E-01/02 on relevant application changes and actual main release candidate; section 4 production-equivalent CSRF/session security smoke required on relevant authentication/security/middleware/session changes and actual release candidate. One agreed primary browser initially, no additional full journey or exhaustive device matrix |
| Main/release | Repeat all required groups on actual merged commit; promote identifiable tested artifact only after success; migration/release smoke and recovery capability evidence before reliance |

Normal F tests already use PostgreSQL; integration group adds narrow database-specific evidence, not a duplicate whole feature suite. Initially run all application groups for implementation PRs until reliable path/dependency selection exists; documentation-only changes need documentation checks. If selection is introduced, shared authorization/storage/domain changes trigger all dependent groups and omitted required checks fail closed. No deployment secrets for untrusted PR code. Do not rely on network-dependent production-provider tests in ordinary PR runs: test compatible adapter locally and verify selected provider before operational reliance and storage-relevant release changes. Record durations before optimizing; no arbitrary CI time promise.

## 13. Coverage policy

Coverage reports are informational. Review evidence for authorization, exact agreement/one-order creation, fixed file provenance/private access, payment arithmetic/history and guarded truthful production. Every critical guard needs a meaningful allowed and denied case; important transaction/remote-file boundaries need rollback/uncertainty evidence. Happy paths and a high percentage cannot compensate for missing race or authorization checks. Do not add tests merely to hit lines or test generated framework code.

## 14. Manual acceptance and pilot validation

Owner/Operator use synthetic records first, then permitted pilot work after policy/reliance gates. Record actor, adopted policy, observed result and unresolved issue for this small checklist:

- Normal print order: chosen inputs, manual review, exact quote/acceptance, production and own-status clarity fit actual work; next action/responsible person usable.
- Clarification and rejected quotation: understandable questions/reply/review and rejection/next action; silence creates no order.
- Pre-production changed quote: owner approves reduced boundary; customer understands new version, hold and renewed agreement; old scope/jobs cannot proceed accidentally.
- Failed print/reprint: actual failure retained, approval and new attempt understandable; quality defect withdraws readiness; no automatic surcharge.
- Cancellation: request versus approved stop/output/residual money distinctions match BD-12; queued work cannot start after final cancellation.
- Fulfillment/closure: offered method/evidence and residual responsibility match BD-11/13; physical completion does not claim payment.
- Payment discrepancy: receipt/refund/correction/excess/unknown/dispute examples match BD-14, preserve history and provide responsible action.
- Fallback/restore: staff can locate/verify recovered linked work and follow incident instructions before reliance.

Business-policy approval is separate from automated correctness and cannot be inferred from a passing test. Use VISION/PRD measures under BD-17: otherwise-valid successful intake ≥95% (technical failures separate from expected rejections/retries); daily open work with current action/responsibility ≥90%; observed active-order status accuracy ≥90% with material updates within one business day. These are proposed pilot targets pending sample/baseline agreement, not CI percentages. Manual tally suffices. Compare handling effort/status inquiries/feedback with equivalent baseline work and maintenance effort; no vanity dashboard or invented benefit target. Accepted agreements/order links and fulfilled/closed physical/payment truth remain acceptance checks. Investigate every loss/disclosure/change incident; quiet pilot is no security/reliability proof.

## 15. Definition of done

A feature may be reported complete only when its agreed scope/acceptance criteria and required policies are satisfied; success/rejection/failure evidence and relevant authorization tests pass; changed migrations work on empty and representative existing PostgreSQL data; relevant storage/concurrency checks pass; user-visible behavior is verified at the appropriate component/browser/manual layer; no unresolved BLOCKER/HIGH correctness, security or integrity review issue remains; and documentation/staff instructions are current.

Record commit/environment, required groups and actual results, including reviewed warnings. Skipped, quarantined failing, unavailable or unrun required tests are missing evidence and block completion; disclose them with remaining work. A planned test or synthetic policy assumption is not a passed test. Feature completion differs from production reliance: BD-06/16 recovery/fallback/security evidence and owner/operator acceptance must also be satisfied before operational use. This document prepares roadmap planning; it proves no implemented feature or production readiness.
