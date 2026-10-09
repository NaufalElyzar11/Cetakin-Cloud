# Cetakin Cloud Domain Model

**Status:** Conceptual MVP model for human review. `AGENTS.md`, `README.md`, `VISION.md` and the reduced `PRD.md` govern this document. PRD requirement IDs and business decisions remain authoritative. This model proposes business vocabulary and consistency boundaries; it selects no implementation architecture, technology or persistence design and authorizes no implementation. Unresolved policies remain unresolved.

## 1. Ubiquitous language

| Term | Business meaning / modeling choice |
| --- | --- |
| Customer | Business party whose request, designs and order are being handled. Record ownership is not proof of intellectual-property ownership. |
| User / actor | Attributable authenticated account/person acting through a current access relationship. BD-01 v1 selects email/password self-registration; User and Customer remain distinct without permanent one-to-one cardinality. Authentication is continuity of access, not legal/design identity. |
| Staff; Administrator; Production Operator | Responsibilities granted to an actor, not three person entities. Owner/Administrator reviews, agrees operational decisions and records money/outcomes; Operator handles authorized production facts. One person can hold several roles. |
| Print Request | Owned submitted work awaiting review, clarification, quotation or disposition; the source of the initial agreement/order. |
| Print Specification | Identified set of requested/agreed preferences, quantity and exact file references. Reviewed, issued and accepted contents must be distinguishable. |
| Design File; Design File Version | Design File names a logical replacement sequence, not an extra lifecycle. Each version identifies an exact submitted file and its provenance/availability. |
| Quotation; Quotation Version | Quotation names a proposal sequence. Its identifiable versions contain fixed issued scope, price and terms. No additional quotation-family entity is needed initially. |
| Agreement | Immutable acceptance fact for an exact quotation version, customer/actor and time. It has no independent approval lifecycle. Initial agreement and current accepted basis are distinguishable. |
| Order | Work committed under an accepted agreement, connecting production, physical outcome and separate payment record. |
| Print Job; Reprint | Identifiable production attempt. A reprint/rework is a new linked attempt after failure or verified defect, not a reset terminal job or separate product. |
| Payment Record; Payment Event | Order's external-money evidence and its separately identified receipts, refunds and corrections. This software transfers no money. |
| Refund; Adjustment | Recorded event kinds with approved evidence, not standalone accounts/workflows. Refund records external repayment; adjustment/correction meaning depends on BD-12/14. |
| Fulfillment; Cancellation | Attached outcome/decision facts with actor, time and evidence. Cancellation request, approval and actual stopped work are different facts. |
| Next Action; Assignment | Small attached responsibility facts: what needs doing and who is responsible/authorized. Neither implies automated scheduling or unrestricted design access. |
| Hold; Discrepancy; Correction | Attached reason, owner and next action that restrict unsafe actions or remove misleading certainty. Corrections retain original facts; notes grant no new agreement or privilege. |

## 2. Entities and identifiable records

Identity means a record can be recognized through time and referenced unambiguously, without choosing an identifier format. The table describes eight business entities plus the minimal User/actor account needed by approved BD-01 v1. Attributes below are conceptual information, not database fields. Customer ownership propagates through the request/order chain; an administrator is an authorized custodian, not the owner of customer designs.

| Entity | Identity, lifecycle and conceptual attributes | Relationships / responsibility / requirements |
| --- | --- | --- |
| Customer | Stable business reference; contact Name, Email and WhatsApp / phone number under BD-01 v1. No CRM or separate customer-state machine. | Owns requests; order ownership follows their accepted request. Current access relationships identify which Users may act; no permanent one-to-one User rule. REQ-001/002/028/033. |
| User / actor (supporting account) | Stable attributable account/person reference; email login identifier, credential continuity, granted responsibilities and revocation/access-change history. First registration establishes one User, one Customer and their initial access relationship. | Acts for an associated customer or under separately approved staff authority. No staff privilege follows from registration. Assisted recovery retains actor/time/reason; staff authority remains BD-05. Historical decision attribution is not reassigned by recovery. REQ-011/033/034/035; BD-01 v1. |
| Print Request | Request identity; PRD lifecycle; customer, current requested specification, submitted time, clarification/review facts, next action and quotation history. | Belongs to one Customer; contains submitted file versions and quotation versions; at most one initial Order. Owner reviews/disposes; customer submits/replies. REQ-001-014/028. |
| Design File Version | Exact version identity; submitting actor/customer, parent request, original name, format, submission time, replacement reference and handling/availability outcome. No printability lifecycle. | Many versions can belong to one request; quotation/order/job refer to exact versions. Customer provenance fixed; task-authorized staff access. Permitted content deletion preserves allowed references/unavailability evidence. REQ-027/028/030/031. |
| Quotation Version | Proposal/version identity; draft then PRD decision states; exact specification/files, quantity, total/currency, terms/deadline, issue time, decision evidence, successor reference. | Contained by originating Request; initial proposal or amendment referencing its Order/current agreement. Staff prepare; customer decides. Accepted Agreement is an attached immutable fact. REQ-008-014. |
| Order | Order identity; PRD lifecycle; customer/request, initial/current accepted basis, agreed specification, job history, production facts/holds, next action, outcome/closure evidence. | Exactly one initial agreement; many attempts; one conceptual Payment Record. Owner governs release/outcomes; operator records authorized facts. REQ-013-026. |
| Print Job | Attempt identity; PRD lifecycle; order, exact agreed basis/files, planned/usable quantity, assignment, actual start/progress/end, outcome/reason and previous-attempt reference for a reprint. | Belongs to one Order. Owner creates/approves retries; authorized operator updates. Failed/cancelled starts still count as historical production starts. REQ-017-020. |
| Payment Record | Identified by its Order; retained event history, approved payable basis and discrepancy/resolution facts. Settlement is derived, not a separate editable lifecycle. | Exactly one Order; zero or more Payment Events; owner-controlled verification/correction. Customer sees limited summary only. REQ-036-039/041. |
| Payment Event | Event identity; kind, order/record, amount/currency, external date/reference/method where available, evidence, recording actor/time and original-event reference/reason for corrections. Once confirmed, retained; correction adds evidence rather than replacing it. | Contained in one Payment Record. Equal amounts do not prove duplication; same-event retry must return the existing event. REQ-036/037/038. |

Clarification replies, review findings, assignment, next action, acceptance, fulfillment, cancellation and corrective evidence need attributable references inside their owning work/history. They are not independent aggregate roots or new case-management products. No standalone Material entity is justified: approved material/color selections and manual availability review meet current scope.

## 3. Useful value objects

Values describe meaning rather than an independent lifecycle. A changed value is new information; it does not replace an issued/accepted snapshot.

| Value | Meaning and constraints |
| --- | --- |
| Money | Amount plus currency; precision/rounding BD-07/14. Only comparable approved currency can form a settled balance. Unknown is not zero; no currency conversion implied. |
| Quantity | Requested/planned quantity is a positive whole number. Actual usable output may be zero; it cannot include failed units. Offering/quantity semantics BD-02, checks BD-10. |
| Customer Contact | Name, Email and WhatsApp / phone number under BD-01 v1; no segmentation/profile subsystem. Email is used for account login, not proof of customer/design ownership. Staff visibility remains BD-05. |
| Print Specification | Material, color, quantity, approved options and exact File References. Issued scope is a fixed identified snapshot; later request edits cannot change it. |
| File Reference | Exact submitted version identity and request/customer provenance; availability is checked separately. A reference remains meaningful when content is unavailable under policy. |
| Quotation Terms | Exact customer-visible conditions and stated deadline if offered; no assumed expiry, tax engine or payment timing. BD-07/09. |
| Payment Summary | Approved payable basis, verified same-currency receipts/refunds/corrections, balance and settlement interpretation. Excess/dispute/unknown information remains explicit alongside it. |
| Public Status | Customer-safe interpretation of recorded stage, hold/error/delay, outstanding action and outcome. It cannot present known defective/unverified work as ready or uncertain money as settled. |

Assignment/Next Action can also be simple attached values with actor/time history; giving them separate entities is unnecessary unless a demonstrated business need appears.

## 4. Candidate aggregate boundaries

An aggregate here is a proposed group protecting a business invariant, not a service, module, table or instruction to load every historical record. Three workflow groups are sufficient candidates. Customer/actor identity and access remain conceptual references; no generic identity-management model is designed.

| Root | Contained business information | Local invariants / reason |
| --- | --- | --- |
| Print Request | Requested/reviewed specification values, Design File Versions, clarification/review facts, Quotation Versions and immutable acceptance facts, disposition/action history. | Own provenance and exact reviewed/issued contents; only eligible proposal can be accepted; replaced files cannot alter fixed quotes. Intake/review/agreement evidence belongs together. |
| Order | Initial/current Agreement references, agreed work, Print Jobs, assignment/holds, physical outcome/cancellation/closure and corrective evidence. | Start/reprint authority, actual production truth, usable output, no terminal restart, immutable prior agreement/job facts. Operational decisions belong to the committed work. |
| Payment Record | Identified external Payment Events, approved payable-basis reference, discrepancy/resolution facts and derived summary. | Retained money evidence, no duplicated verified event, approved arithmetic and no fabricated settlement. Money history remains independent of physical completion. |

A converted Request stays terminal as intake, while its retained quotation history can append a supported pre-start Order amendment. This does not reopen/edit the original intake or accepted contents. Quotation and Agreement do not need additional roots; their identity/evidence remains explicit inside the proposed groups.

Some invariants cross these candidates and require one **guarded business outcome**:

- Initial acceptance fixes the exact Agreement, converts the Request and establishes exactly one retrievable linked Order. Never report completed acceptance/conversion with a missing order. Interrupted work remains explicitly unresolved; retry resolves the same outcome (REQ-011/013/041).
- Supported amendment issue/acceptance and actual job start must respect the same current basis, never-ever-started condition and hold. Accepted amendment updates the same Order, retains prior Agreements, invalidates obsolete queued jobs and changes the payable basis used by Payment Summary together. No changed-scope job or mixed old/new payable view is eligible (REQ-014/016/017/038/041).
- Cancellation/fulfillment/closure must reconcile actual job outcomes, blocked work and truthful physical/financial facts. No successful cancelled/closed order with residual startable jobs or hidden disputed balance (REQ-019/023-026/039).

These requirements reject eventual successful-but-unlinked handoffs. They choose no transaction mechanism, distributed coordination or event-driven architecture. If the candidate boundaries hinder these simple outcomes, later design may combine them; business invariants take precedence over diagram purity.

## 5. Relationships and cardinalities

```text
User 0..* ---- current ownership/access relationships ---- 0..* Customer
Customer 1 ---- 0..* Print Request
Print Request 1 ---- 1..* submitted Design File Versions
Print Request 1 ---- 0..* Quotation Versions
Issued/accepted Quotation Version 1 ---- 1 fixed Specification ---- 1..* exact File References
Quotation Version 1 ---- 0..1 immutable accepted Agreement fact
Print Request 1 ---- 0..1 initial Order
Order 1 ---- 1 initial Agreement; 1 current accepted basis
Order 1 ---- 0..* retained accepted amendment facts
Order 1 ---- 0..* Print Jobs
Print Job 1 ---- 0..1 earlier attempt reference (reprint/rework)
Order 1 ---- 1 conceptual Payment Record ---- 0..* Payment Events
```

The fixed-specification/file cardinality applies once a quotation is issued, including its accepted/other terminal versions. A draft can be incomplete and editable; issue-ready contents are required before issuance.

An initial Agreement belongs to exactly one resulting Order; every accepted amendment refers to that same Order. Each Print Job belongs to one Order, and any earlier attempt it references belongs to that Order. Job file references must belong to its Order's customer/originating Request and exact authorized agreed basis; identical content does not authorize sharing/deduplication across customers. Original Payment Event order/customer provenance cannot be overwritten or destructively reassigned, even when an attribution error is noticed. Authorized corrections retain the original and add linked evidence under BD-14; their arithmetic remains undecided.

One conceptual Payment Record exists even before any receipt: its empty event history and current agreed payable basis produce unpaid/no-payment-due truth, not an invented receipt or another lifecycle. This logical cardinality does not prescribe a separately provisioned record.

Each Request/Order has one business Customer. BD-01 v1 initially provisions one User, one Customer and one relationship on registration; that onboarding outcome does not constrain the long-term relationship cardinality. Current explicit relationships determine which Users can act for each Customer. No multi-customer management UI, invitation or delegation product is introduced. An actor may have several staff/customer responsibilities, but each action requires its applicable permission. Active work has a Next Action/responsible person; incomplete assignment is visible rather than inventing a staff department (REQ-007).

File cardinality describes submission/evidence references, not permanent availability of all contents. Format/count limits and permitted retention/deletion remain BD-03/06. Job/output details do not introduce per-file inventory or salvage allocation.

## 6. Enforceable business invariants

1. Current User-to-Customer relationships and task permissions govern every list, read, file action, decision and change. Authentication/email matching alone grants no Customer ownership, design rights or staff privilege. Logout/expiry/revocation deny subsequent access; quotation acceptance requires the authenticated customer relationship, never substitute staff authority. Earlier access or operator assignment cannot confer admin/money privilege (REQ-011/033/034; BD-01 v1).
2. A successfully submitted Request records a valid approved specification and completed supported files belonging to that customer/request at submission. Failed upload is not usable; same-attempt retry does not add a request. Later permitted content deletion retains the availability/evidence rules below (REQ-001/002/027/028/031).
3. Quotation issue requires sufficient review. Issued contents are fixed; replacement is another version. Acceptance/rejection identifies the customer, exact version and time; stale, rejected, withdrawn, superseded or expired proposals cannot be accepted (REQ-004/008-012).
4. Exactly one initial accepted proposal produces one traceable Order; no orphan successful conversion or duplicate initial order. Accepted history remains inspectable (REQ-009/011/013/041).
5. Native amendment requires queued Order, current prior basis, one eligible proposal and **no job ever started**, even a later failed/cancelled one. Changed work stays held until renewed acceptance and corrected release; obsolete queued jobs cannot start (REQ-014/016/017).
6. After any production start, scope/file/price changes and reprint surcharges are held owner exceptions; original accepted basis stays frozen. Notes cannot authorize changed work. Cancellation/replacement agreement is an unresolved option, not an automatic rule (REQ-014/020/021/030; BD-08).
7. Actual authorized start changes production truth; payment/acceptance alone proves no release. Terminal order/job cannot restart; reprint is a new approved linked attempt with old failure retained (REQ-016-020/024).
8. Ready/fulfilled output meets agreed usable quantity/checks and all jobs are terminal. Failed units never count. Known defect immediately removes effective public readiness and holds fulfillment; same-scope rework requires owner approval/actual start. It may reference a previously succeeded attempt with later defect evidence; original success/history remains, and affected output is unverified until resolved (REQ-019/023/026).
9. Cancellation request is not approval or stopped printing. Actual stop and cancelled queued work precede final cancellation; physical output and financial responsibility remain visible (REQ-024/035/038).
10. Physical fulfillment, payment settlement and closure are separate. Closure uses adopted policy and explicit residual ownership; unresolved amount/dispute/error cannot disappear (REQ-023/025/026/039).
11. File replacement retains exact issued/accepted references. Missing content blocks affected work; approved deletion preserves permitted evidence/unavailable marker without promising permanent content retention (REQ-028/030/031/043).
12. Confirmed money events and consequential history cannot be silently erased/overwritten. Refund/correction retains reason/evidence; retry records the same event once. Unknown/currency mismatch/excess is explicit, not silently settled (REQ-035-039/041).
13. A factual correction preserves original evidence, responsible owner and affected-action hold; it grants neither arbitrary reopening nor amended agreement. Safe manual resolution needs adopted policy, unrelated accurate work remains operable (REQ-025/026).

## 7. Lifecycle evaluation

Retain the PRD's distinct business stages initially; compressing them into generic open/done flags would lose next-action, agreement or physical/financial distinctions. Listed transitions remain proposed and guarded by the invariants/policies above. All unlisted or unauthorized transitions fail unchanged, and consequential history is retained (REQ-026/035).

| Model | Valid transitions and authority | Evaluation / simplification |
| --- | --- | --- |
| Print Request | Customer: new -> submitted. Administrator: submitted -> awaiting clarification; reviewed response -> submitted; submitted -> quoted; quoted -> submitted/awaiting clarification after pending quote invalidation; open -> declined/cancelled with reason/policy. Customer initial acceptance outcome: quoted -> converted. | Six PRD states useful. Quoted means quotation-stage history, not proof a current eligible quote exists after rejection/expiry. Converted/declined/cancelled intake never reopens. Review-in-progress is not another state. |
| Quotation Version | Administrator: draft -> issued; issued -> superseded/withdrawn. Customer: eligible issued -> accepted/rejected. Stated deadline: issued -> expired eligibility, whether administrator/event records it. | Seven states preserve different decision reasons without a scheduler. Initial acceptance needs no existing accepted basis; amendment checks apply only to amendments. Draft editable; terminal versions never reactivated; repeat acceptance retrieves prior result. |
| Order | Initial agreement -> queued; actual released job start -> in production; owner verifies output/terminal jobs -> ready for fulfillment; owner-authorized same-scope actual rework -> in production; owner fulfillment -> fulfilled; owner approved stop/outcome from queued/production/ready -> cancelled; adopted accurate fulfilled/cancelled outcome -> closed. | Six states retained. Pre-start amendment remains queued. Failed/reprint/payment/hold are facts, not extra Order states. Historical ready stage may remain recorded while a defect warning makes effective status not-ready; no fulfillment until resolved. No native terminal reopening. |
| Print Job | Administrator creates queued; authorized staff actual start -> in progress; actual outcome -> succeeded/failed; queued -> cancelled with authority/reason; in progress -> cancelled only after actual stop. | Five states. In progress means started/nonterminal, not continuous printer activity: interruption/hold must be visible. Distinct pause/resume is optional PRD SHOULD, not modeled as required. Terminal attempts never restart. |
| Payment | Verified receipt/refund/correction or supported agreed payable change recalculates summary; administrator records/resolves dispute with evidence. | Remove a redundant editable state machine. Unpaid/partial/paid/no-payment-due are derived interpretations; overpaid/unknown/currency inconsistency and dispute stay explicit alongside balance. No arbitrary paid toggle or cancellation-to-paid transition. |

Staff can preserve/correct factual evidence without inventing forbidden state transitions. Terminal-error resolution and any permitted subsequent action require BD-13; deferred reopening tooling does not suppress known error warnings or block unrelated accurate closure.

## 8. Meaningful domain facts

Events describe business facts that merit retained attribution; they are not requirements for a broker, event sourcing, notification automation or separate event entities everywhere. Each relevant fact identifies its record, actual actor/named deadline event, time, evidence/reason and prior facts when correcting them. Do not include confidential design contents/secrets (REQ-035).

| Facts | Business meaning |
| --- | --- |
| Request Submitted; Clarification Requested/Replied; Request Declined/Cancelled | Valid intake or explicit reasoned review/disposition; silence emits no agreement/disposition. |
| File Version Submitted/Replaced; File Unavailable/Permitted Deletion Recorded | Exact provenance, replacement or truthful content availability; no silent accepted-reference swap. |
| Quotation Issued/Superseded/Withdrawn/Rejected/Expired | Fixed identifiable proposal and eligibility outcome; expiry only with stated deadline. |
| Initial Agreement Recorded / Order Established | Completed guarded initial acceptance outcome and exact Request/Order links. An unresolved conversion is not a successful outcome fact. |
| Pre-start Amendment Agreed; Late Change Held | Same-order renewed agreement under never-started guards, or an owner exception preserving original basis. |
| Job Started/Interrupted/Succeeded/Failed/Cancelled; Reprint Approved/Created | Actual attempt facts, usable output, retained failure and linked retry. Cancellation fact confirms physical stop when needed. |
| Readiness Confirmed/Questioned; Fulfillment Recorded; Order Cancelled/Closed | Verified or explicitly uncertain physical outcome, separate from money; owner authority/evidence required. |
| Receipt/Refund/Correction Recorded; Discrepancy Raised/Resolved | Verified external money/retained corrective evidence, not an executed payment or hidden write-off. |
| Access Granted/Revoked; Consequential Fact Corrected | Authorized responsibility change or append-only evidence correction; no impersonation/arbitrary reopening. |

## 9. Authorization boundaries

| Actor responsibility | Allowed business actions / visible information | Boundary |
| --- | --- | --- |
| Customer | Own request/contact, permitted files, clarification reply, eligible quote decision, agreed scope/public status/payment summary, cancellation/deletion request. | No other customer's records, staff notes/diagnostics/financial references, money verification, privilege grants or direct fulfillment/closure. |
| Owner / Administrator | Task-authorized review/design access, quotations, assignment/job creation/reprint approval, release, verified money, approved cancellation/fulfillment/closure, authorized grants and corrective evidence. | Cannot impersonate customer acceptance, silently edit agreements/events or bypass file/hold/terminal guards. Access is not automatic entitlement to every sensitive design. |
| Production Operator | Explicitly authorized work/specifications/designs, required instructions/contact, factual progress/output/failure/stop and reprint proposal. | No quote prices/payment record/customer directory, job creation by default, cancellation approval/fulfillment/closure, access grants or unrestricted internal history. |

Ownership checks cover lists, details, metadata and direct file/record actions. Permission is evaluated for the action being attempted; previously opened data grants no future right. Public Status and customer payment summary are limited interpretations, never unrestricted copies of internal records. One human with several roles uses the permission applicable to each action. An Administrator who is also a Customer may accept only as the verified customer for their own eligible quote; staff permission never substitutes for that relationship (REQ-011/022/033-035).

## 10. Business decisions and adoption status

BD-01 v1 is approved (2026-10-10; PRD section 7.1). BD-02–17 remain open. Conceptual modeling/synthetic examples can proceed; dependent implementation and real reliance require adoption plus separate authorization. No other default below is silently selected.

| Decision | Affected concept / unfinalized rule | Safe now; what waits |
| --- | --- | --- |
| BD-01 Identity/contact — ADOPTED v1 | Email/password account, required contacts, initial provisioning, current relationships and attributable customer decisions. | Customer identity policy gate resolved; no permanent one-to-one rule. Administrative assisted recovery awaits BD-05 authority; no verification-email or self-service password reset. No implementation is started by policy approval. |
| BD-02 Offerings/quantity | Specification/Quantity completeness and valid selections. | Model bounded preferences/manual review; intake waits for actual options/required fields/quantity meaning. |
| BD-03 Upload limits/checks | File Version usable handling and supported attempt. | Exact versions and STL/3MF concepts; upload waits for count/size/handling rules. |
| BD-04 Rights/confidentiality | Permission to print, refusal and task-sensitive access. | Record provenance, assume no IP transfer; real design intake/printing waits for handling/permission policy. |
| BD-05 Staff authority | Assignment, design visibility, grants and administrator/operator permissions. | Distinct responsibilities; first staff access/review waits for approved necessary privileges, including a first review slice. |
| BD-06 Retention/deletion | Content availability, preserved evidence and recovery copies. | Model exact references/unavailable marker; real retention/deletion waits for durations/actual assisted process. No indefinite retention promise. |
| BD-07 Terms/currency | Quote eligibility, Money precision and total/validity. | Fixed contents/explicit deadline if offered; issue/money behavior waits for terms/currency/precision. No expiry duration/tax engine. |
| BD-08 Change boundary | Never-started amendment, holds and late change resolution. | Frozen basis and held exceptions; supported change behavior waits for owner acceptance of limitation/resolution. No forced cancellation/replacement default. |
| BD-09 Release/payment | Deposit/timing/method/proof and work-start eligibility. | Separate production/money facts; release/payment behavior waits. No inferred prepayment requirement. |
| BD-10 Failure/retry | Cause, usable checks, reprint approval/cost. | Retain failure history/next action; retry rules wait. No surcharge; delegation/salvage remains deferred. |
| BD-11 Fulfillment | Offered method and evidence of handoff. | Distinct physical outcome; fulfillment waits for actual method/proof. No shipping integration. |
| BD-12 Cancellation/refund | Authority, stopped work, produced goods and amount. | Request/evidence and original balance; approval/refund treatment waits for minimum manual policy, not automatic tariff. |
| BD-13 Closure/errors | Residual responsibility, corrected truth and safe subsequent actions. | Error warning/hold/original history; closure and safe error resolution wait. Rare reopening machinery can defer independently. |
| BD-14 Money arithmetic | Receipt/refund/correction meanings, approved adjustment/precision/excess. | Identified retained events/unknown-not-zero; recording/derived settlement waits for approved examples. No arbitrary quote-price override. |
| BD-15 Stale automation | Age-triggered disposition. | Manual next action/withdrawal; automation deferred, no silence-to-agreement/cancellation. |
| BD-16 Reliance profile | Recovery ownership/fallback/hours/ordinary responsiveness. | Domain/synthetic work unblocked; real reliance waits for verified recovery/access and useful conditions. Numeric SLA deferred. |
| BD-17 Validation | Baseline/sample/window/useful improvement threshold. | Lightweight manual evidence possible; pilot/value claim waits for meaningful baseline/comparison. No analytics build. |

BD-01's former BLOCKING-NOW gate is resolved by explicit approval, not inferred from this model. BD-02/03 still block separately authorized private intake; other decisions gate their named features or real use. BD-05 continues to gate staff authority, including administrative assisted recovery.

## 11. Complexity risks and deliberate restraint

- Avoid a separate root/state machine for every noun. Roles are responsibilities; Agreement is an immutable accepted fact; Reprint links attempts; Refund/Adjustment are money event kinds; Payment Summary is derived.
- Keep only three workflow consistency candidates. Their important cross-boundary outcomes remain guarded; no distributed architecture, generic event platform or speculative entity/service hierarchy follows.
- Preserve exact history instead of adding negotiation/approval engines. Native amendment stays pre-start; late work/price proposals are held, never executed from a note. No parallel proposals, partial continuation or readiness-reuse engine.
- Do not equate physical completion, payment and closure. Retain discrepancies and correction evidence without building accounting, salvage, general audit or terminal reopening products.
- Files have accountable versions and availability, not permanent retention by assumption. Customer access/provenance is not legal ownership; confidential handling/assisted deletion needs owner policy.
- No inventory, storefront, printer integrations, automated pricing/slicing, notification suite or analytics entities are introduced. Future evidence can justify revisiting simplifications; exclusions are not future promises.

The result is a reviewable business model, not code, approved policies, a technical architecture or proof of production readiness. Later architectural work must preserve these behaviors and record significant decisions as ADRs under repository rules.
