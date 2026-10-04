# Cetakin Cloud MVP Product Requirements Document

**Status:** Proposed requirements for human review. This document does not authorize implementation or establish unresolved business policies.

**Authority:** `AGENTS.md`, `README.md`, and `docs/VISION.md`. The vision's custom-print workflow and MVP exclusions govern this PRD. Requirements below propose observable product behavior; owner decisions identified as `BD-xx` must be resolved before dependent behavior is implemented or used operationally. No framework, database, hosting provider, or implementation architecture is selected here.

## 1. Product scope

Cetakin Cloud connects a customer's custom-print request, reviewed specification, identified quotation and agreement, order, production work, recorded payments, fulfillment, and final outcome. It supports a small operation; customer, administrative, and production responsibilities may belong to the same person.

### MVP

- Customer access to their own requests, STL/3MF files, issued quotations, quotation decisions, orders, customer-visible status, and payment summary.
- Request intake with contact information, material, color, quantity, printing options, and manual staff review of completeness, feasibility, file suitability, and material availability.
- Clarification, unsuitable-work decline, manual quotation preparation and revision, explicit customer acceptance/rejection, and one traceable order from an accepted quotation.
- A manually managed production queue and individual print jobs, including delays, failed attempts, reprints, and truthful quantity/progress records.
- Staff payment recording, refunds/adjustments where applicable, discrepancy handling, fulfillment, cancellation, and closure under owner-approved policies.
- Minimum contact details needed for this workflow; restricted retained files; important agreement, production, authorization, and financial history; current next action and responsible staff member for open work.

Basic work visibility means lists and record details sufficient to identify outstanding work. It does not require a separate analytics dashboard. Availability of materials/colors is checked manually; no stock quantities or consumption ledger are required. Clarification is a response attached to a request, not a general messaging service. No outbound notification channel is required by this MVP; customers can inspect their own current records, and staff can identify work awaiting action.

### Post-MVP candidates

Own-product sales, expanded inventory, notifications, and additional customer conveniences may be reconsidered only after evidence shows a gap in the core workflow. These are discovery candidates, not committed requirements or delivery promises. There are no approved post-MVP features in this PRD.

### Explicitly out of scope

All vision exclusions remain excluded: storefront/catalog checkout and own-product sales management; automated slicing, geometry repair, 3D preview, instant pricing or AI recommendations; printer telemetry/control and automated scheduling; full filament stock accounting, suppliers/purchasing/consumption/replenishment; full CRM, marketing, loyalty and segmentation; integrated payment processing, invoicing/accounting suites and tax automation; comprehensive notification automation, advanced dashboards/BI and a general-purpose audit platform; multi-business tenancy, marketplaces, subscriptions, native mobile apps and generalized manufacturing/ERP features.

Manual quotation totals and recorded payment methods do not introduce a tax engine or payment gateway. Basic workflow history does not introduce a general-purpose audit platform. File intake does not guarantee that a design is printable or that Cetakin will accept it.

## 2. Personas and permissions

Roles describe responsibilities, not separate people or departments. A person may explicitly hold multiple roles. Every privileged action records the person's identity and effective permission. The customer ownership/access mechanism and staff assignment policy remain owner decisions (`BD-01`, `BD-05`); ownership checks are mandatory regardless of the eventual access method.

| Record/action | Customer | Business owner / administrator | Production operator only |
| --- | --- | --- | --- |
| Contact details and requests | Own details and own requests; submit and respond to clarification | Business requests and minimum customer details needed to review/manage them | Only production contact/instructions explicitly authorized for assigned work; no unrestricted customer directory |
| Uploaded designs | Own permitted files; request replacement/deletion under policy | Authorized business files needed for review/order management under design-access policy | Only designs and specifications needed for explicitly assigned/authorized production work |
| Quotations | Own issued versions, terms and decisions; accept/reject current eligible version | Prepare, issue, revise, withdraw, and inspect agreements; cannot impersonate customer agreement | No prices, quotation terms, or customer agreement actions by default; access agreed production specifications only |
| Orders | Own public status, agreed specification, fulfillment outcome and payment summary | Manage all business orders and exceptions within approved policy | See assigned/authorized production orders and queue entries; update production progress only |
| Print jobs | Customer-visible progress/outcomes of own order; no internal failure analysis | Assign/manage work, create normal jobs, approve exceptions, inspect history | Start/update/pause/finish/fail authorized jobs and record delays; propose reprints; create only expressly delegated approved reprints, and start only authorized released work |
| Payments | Own payable amount, recorded payment/refund totals and current settlement/discrepancy summary | Record and correct payments/adjustments, resolve discrepancies under policy | No payments, prices, financial corrections, refunds or financial summaries by default |
| Fulfillment/cancellation/closure | Request cancellation; inspect own approved outcome | Approve/decline cancellation, record fulfillment and close under policy | Record production facts; no cancellation approvals, fulfillment or closure authority by default |
| Work history and permissions | Own customer-visible agreement/status history | Relevant internal history; grant/revoke staff permissions with traceability | Relevant production history only; no role administration or unrestricted audit access |

Customer-facing fields must be identified explicitly. Internal staff notes, financial references, sensitive contact information of others, and administrative history do not become public because they belong to the customer's order. Customer payment visibility does not expose staff-only reconciliation notes or external receipt credentials.

## 3. Functional requirements

Stable IDs identify requirements independently of section order. `BD-xx` references in Sections 4-13 also qualify related requirements. An unresolved decision blocks only dependent behavior; a proposed default is not an approved policy. Acceptance scenarios in Section 11 supplement these individually verifiable requirements.

### Intake and review

| ID | Requirement |
| --- | --- |
| REQ-001 | A customer can submit a request containing their established contact/ownership reference, at least one successfully uploaded supported design file, material, color, positive whole-number quantity, and the owner-approved printing-option fields. Submission rejects missing required fields, unsupported selections and invalid quantities with field-specific reasons; the required fields/selections are defined by `BD-02`. |
| REQ-002 | A successful submission returns one identifiable request linked to its customer, specification and file versions. Retrying the same submission after an uncertain response retrieves that same request. A deliberately separate repeat order/request remains possible; similarity alone must not cause automatic deletion or merging. |
| REQ-003 | Rejected validation or a failed upload does not report a submitted request as complete. The customer sees which part failed and can correct/retry it without a second successfully submitted request from the same attempt. Durable draft saving is not required. |
| REQ-004 | An administrator can review the submitted specification and authorized files and record findings for completeness, print suitability, material/color availability and the next action. No quotation can be issued until the required review checks are recorded as sufficient for the proposed scope. Manual inspection is sufficient; automated printability analysis is excluded. |
| REQ-005 | An administrator can request specific clarification; the customer can supply an identifiable reply and replacement specification/files. The unanswered questions, reply, actor and timestamps remain linked to the request. A request awaiting required clarification cannot receive a quotation until staff review the response and establish sufficient specifications. |
| REQ-006 | An administrator can decline unsuitable work with a customer-visible explanation and an optional separate internal reason. The request retains its file/agreement history and cannot become an order from the declined state. |
| REQ-007 | Each open request and order can record a next action and responsible staff member. Waiting for a customer still has a staff member responsible for follow-up. Staff can list open work and identify missing action/assignment information. No response does not automatically accept, decline or cancel work; any owner-approved stale-work treatment is explicit and recorded (`BD-15`). |

### Quotations and agreement

| ID | Requirement |
| --- | --- |
| REQ-008 | An administrator can prepare a manual quotation identifying the customer/request, exact specification and file versions, quantity, price total/currency, applicable terms, and any stated validity deadline. Terms, currency handling and expiry policy require `BD-07`. The quotation is distinguishable from an automated estimate; staff review is required. |
| REQ-009 | Every issued quotation has a unique identifier/version and issue timestamp. Issuance fixes its customer-visible contents, specification/file references and price/terms. Changing an issued quotation creates a new version; its prior contents remain inspectable. |
| REQ-010 | Issuing a replacement for an unaccepted quotation marks the previously issued version superseded and identifies its successor. Only the current eligible issued version can be accepted. A withdrawn, expired, superseded, rejected, draft or already accepted version cannot create a new agreement or new order. |
| REQ-011 | A customer can explicitly accept their own current eligible quotation. Acceptance records customer identity, exact version/contents, decision and timestamp. Repeated acceptance/retry returns the original agreement/order result; simultaneous acceptance, revision or withdrawal must result in one valid outcome without a stale agreement. |
| REQ-012 | A customer can explicitly reject their own current eligible quotation; record identity, version, decision and timestamp, plus optional customer feedback. Rejection creates no order and does not silently decline/cancel the request. Further review or a new quotation requires a visible staff action. |
| REQ-013 | Acceptance of an initial quotation produces exactly one order linked to that agreement, customer, request, and agreed file/specification versions. The customer/staff can retrieve the resulting order. If acceptance/order completion fails, report the unresolved result, recover it without another agreement/order, and do not present a missing order as success. |
| REQ-014 | For a material scope/price change after acceptance, an administrator creates a separately identified amendment quotation linked to the same order and its current accepted agreement. Amendment preparation, issue and acceptance are limited to queued, in-production or ready-for-fulfillment orders. Only one issued amendment is currently eligible per order: a competing successor supersedes the earlier unaccepted amendment. Acceptance requires its prior-agreement reference to still equal the order's current agreement; stale drafts/proposals require re-review and reissue against the latest basis. Acceptance retains earlier agreements and job facts, updates that same order only, and reassesses usable output, readiness and pending/active job authorization under `BD-08`. Changed work remains blocked until this reassessment; insufficient/unverified output moves a ready order back to queued rather than falsely leaving it ready. |
| REQ-015 | A rejected, withdrawn, superseded, expired or unanswered amendment leaves the prior accepted agreement unchanged. Proposed changed work cannot start. Staff record what work is held and why; previously agreed work may continue only after an administrator confirms it is unaffected and permitted under `BD-08`. Customer cancellation requests and refund questions remain separate decisions. |

### Orders, production and fulfillment

| ID | Requirement |
| --- | --- |
| REQ-016 | An accepted order enters the manual production queue with its agreed output quantity/specification, current next action, responsible person and current payment state. Production release follows explicit owner-approved conditions (`BD-09`); neither acceptance nor a recorded payment silently proves release eligibility. |
| REQ-017 | An administrator assigns work and creates normal print jobs linked to one order, agreed file/specification versions and planned output quantity. An operator may create only an approved reprint when creation is expressly delegated under `BD-10`. Operators see only authorized work. Starting/resuming requires a queued or in-production order, authorized current scope, release conditions and no blocking hold. Ready-for-fulfillment rework requires a documented administrator-approved ready -> in-production transition coordinated with the actual authorized job start. No job starts/resumes for a fulfilled, cancelled or closed order. |
| REQ-018 | An authorized operator can record job start, progress, actual pause/resumption, success, failure and stopped-work cancellation with actor/timestamp and relevant reason. A completed/failed/cancelled attempt remains in history. A queued delay is recorded as a delay, not a falsely started or failed print. |
| REQ-019 | Job success records verified usable output quantity. Failed output does not count as fulfillment-ready quantity; partial usable output from a failed attempt must be separately identified and confirmed by authorized staff. An order becomes ready for fulfillment only when authorized staff confirm the currently agreed usable output/checks and all its jobs are terminal. Unneeded queued jobs must be cancelled with a reason; active jobs require actual completion/stopped-work confirmation. Accepting changed scope preserves old output/job facts and requires reassessment. If retained output is then verified sufficient, a queued amended order can become ready without fabricating a new print/job start. |
| REQ-020 | Failed printing records the failure and next action. A reprint creates a new job linked to the failed attempt and same order, preserving failure evidence. Approval, responsibility and any extra charge follow `BD-10`; an extra charge/scope change requires renewed quotation agreement. Failure or reprint alone does not cancel an order, erase payment records or increase the customer's agreed price. |
| REQ-021 | Staff can record a production delay/hold, reason, responsible person and next action. The customer sees an approved customer-visible delay/status explanation. Existing promised dates must not silently change; updates retain the old statement and reason, and a term/scope change requiring agreement follows REQ-014. No automatic scheduling or promised turnaround is required. |
| REQ-022 | A customer can inspect their own order identifier, current agreed specification, public production/status explanation, outstanding customer action, fulfillment outcome and payment summary. Internal notes/job diagnostics and other customers' records remain inaccessible. Displayed status reflects recorded work; delay, cancellation and payment uncertainty must not appear as successful completion. |
| REQ-023 | An administrator can record fulfillment only from ready-for-fulfillment work with all jobs terminal, using method, timestamp and required evidence under `BD-11`. Physical fulfillment is separate from payment settlement/closure and does not automatically mark an unpaid order paid. Pending issued amendments must be explicitly resolved/withdrawn before fulfillment; terminal order stages cannot accept an operational amendment or start residual queued work. |
| REQ-024 | A customer can request cancellation with a reason; an administrator records approval/decline and explanation under `BD-12`. The request itself does not cancel an active print. Approved order cancellation prevents new jobs and requires confirmed stopped/terminal jobs; record the physical outcome and any unresolved payment/refund decision without erasing work or setting the balance to zero. |
| REQ-025 | An administrator can close a fulfilled or cancelled order only when owner-approved closure conditions (`BD-13`) are met and recorded: physical outcome, all jobs terminal, current payment state, and explicit treatment of unresolved amount/dispute. Closing never manufactures a payment or erases a balance. Pending factual correction prevents closure until its approved outcome is recorded. |
| REQ-026 | Lifecycle changes reject unauthorized actors, unlisted transitions and missing guards, leaving state unchanged. Successful transitions retain from/to states, actor/time and required reason/evidence. Corrections retain original facts and the correcting actor/time/reason. Erroneous fulfillment/cancellation/closure correction requires `BD-13` to define its resulting state, permitted transition and subsequent actions; dependent correction, rework and closure are blocked until that policy is approved and added to this model. A note alone cannot reopen terminal work. Genuine new production scope after fulfillment, cancellation or closure requires a new request; later traceable financial events remain separate. |

### Files and access

| ID | Requirement |
| --- | --- |
| REQ-027 | Intake supports STL and 3MF within owner-approved limits (`BD-03`). An upload must finish and satisfy agreed format/handling checks before being attached as usable. Unsupported type, misleading format, incomplete/corrupt transfer or exceeded limits receives a specific rejection without executing embedded content or exposing the rejected design to other users. Acceptance of an upload is not proof of printability. |
| REQ-028 | Each retained file records submitting customer, request association, identifiable file version, upload time, original filename and handling status. A quote/order uses explicit agreed file versions, not an unexplained reference to whichever file is currently newest. File legal ownership and permission to print follow `BD-04`; submission grants no assumed transfer of intellectual property. |
| REQ-029 | A customer may access only their own permitted designs; administrators/operators access only designs authorized for their business task. Operator assignment does not grant blanket access to the file collection. Direct retrieval, file metadata and replacement/deletion actions apply the same authorization boundary as the request/order. |
| REQ-030 | Replacement adds a new identified file version and keeps the old agreement associations/history. A pre-acceptance change affecting a quotation withdraws/supersedes it before fresh review/issue. After acceptance, changing the agreed production file requires an amendment agreement; a new upload cannot silently replace the file a job/order is authorized to print. |
| REQ-031 | File deletion/retention follows the approved policy (`BD-06`), including customer requests, agreement evidence, operational recovery copies, timing and exceptions. Do not promise permanent retention or unrestricted deletion. If deletion is prohibited/deferred, record the reason and next action; if permitted and completed, retain permitted association/history evidence and a truthful unavailable-file marker. Operational intake and deletion behavior are blocked until this policy is approved. |
| REQ-032 | A failed upload provides no usable-file association, quote or print-job authorization. Retrying must not silently substitute another customer's file or duplicate a completed submission. A file that becomes unavailable after submission is visibly marked with responsible staff and next action; quoting/starting affected work is blocked until authorized recovery or renewed customer agreement resolves it. |
| REQ-033 | Sensitive designs and customer/internal records cannot be inspected, listed, downloaded, accepted, modified or deleted without the relevant ownership/role permission. Denial covers guessed identifiers and direct access; denial reveals no confidential contents or ownership details. |
| REQ-034 | Administrator permission changes require an authorized administrator and retain actor, affected person, before/after permissions, reason and timestamp. Revoked access cannot perform later privileged actions; customer/operators cannot grant themselves administrative privileges. Permissions must be checked for each action, including changes after a user previously opened a record. |
| REQ-035 | Important agreement, assignment, state, payment, fulfillment, cancellation, closure, file-access/deletion and permission actions are traceable to an identified actor or named system event. Record security-relevant denials with attempted action/time and actor when known, without logging design contents, secrets or unnecessary private information. Audit records are not writable by customers/operators and have no ordinary destructive edit action. Corrections are additional traceable entries. |

### Recorded finances

| ID | Requirement |
| --- | --- |
| REQ-036 | An administrator can record a payment against an identified order with amount/currency, receipt date, method/reference where available and recording actor/time. Customer/operator input cannot itself establish a verified receipt. Required evidence, currency precision, permitted methods and timing depend on `BD-07`, `BD-09` and `BD-14`; no payment is executed by this product. |
| REQ-037 | Recorded receipts, refunds and approved monetary corrections remain separately identifiable; corrections state the original event, reason and actor/time. They do not silently overwrite/delete prior events. An attempted duplicate reference/retry is flagged and requires confirmation of a distinct event or returns the existing event; two legitimate equal payments remain possible. |
| REQ-038 | Each order displays current agreed payable amount, net recorded receipts, refunds/adjustments, outstanding balance and derived unpaid/partially-paid/paid status; excess receipts are visible as overpaid/unresolved. Recalculation follows every financial event or accepted payable change. Currency mismatch or an unexplained amount cannot be silently combined as settled. The arithmetic and event meanings must be defined under `BD-14`. |
| REQ-039 | Staff can record a payment discrepancy or dispute with amount/context, responsible administrator and next action; resolving it records evidence/reason. The flag is visible alongside the recorded balance and does not imply no receipt or authorize a refund. An unknown amount is explicitly unresolved, not represented as zero. |
| REQ-040 | Refunds, cancellation charges, compensation and receivable adjustments record only owner-approved outcomes (`BD-12`, `BD-14`); any agreed scope/price change uses REQ-014. Recording a refund does not transfer funds. Production, fulfillment and closure gates reference approved business policy without imposing full prepayment or inventing when money is due. |

### Integrity, reliability and validation

| ID | Requirement |
| --- | --- |
| REQ-041 | Agreement/order/file/job/payment associations preserve customer ownership and their exact agreed references. Concurrent updates or repeated commands cannot create multiple orders for one initial agreement, produce an orphan confirmed receipt, lose a terminal job outcome, or accept stale quotation contents. Conflicting actions return a truthful resolution/retry message. |
| REQ-042 | Every action in the permission matrix has positive/denied acceptance checks; ownership checks cover record lists, details, files and changes across two customers and operator/admin responsibilities. Operational reliance requires all such relevant checks to pass, with zero confirmed unauthorized disclosure/change and no unresolved blocking failure. |
| REQ-043 | Submitted files and important records are recoverable together with their ownership/agreement links; no allowance for loss of acknowledged submissions or their required associations is introduced. Before reliance, demonstrate recovery of a sample submitted request/order/file, verify payments/history/permissions, and document the fallback when recovery is unavailable. Proposed restoration timing requires `BD-16`; a missing submitted file or confirmed record is not successful recovery. |
| REQ-044 | User actions report actual success, failure or an explicitly unresolved result. A retry after interruption cannot silently repeat an agreement, order or financial event. Important inconsistencies and missing associations block dependent actions and identify the staff next action, rather than claiming the workflow is complete. |
| REQ-045 | Proposed basic-performance acceptance target: at least 95% of ordinary list/detail/status/quotation-decision actions complete within 3 seconds under 5 concurrent users on an agreed representative pilot dataset/test connection. Upload transfer and manual staff work are measured separately. The test profile and target require owner approval (`BD-16`) and are not claims about current performance or scale. |
| REQ-046 | Proposed service expectation: at least 99% availability during owner-defined operating hours over a pilot month, excluding agreed maintenance, measured by whether a customer can retrieve own status and staff can retrieve/update work. Document outage time, fallback and recovery responsibility; agreed availability/maintenance measurement rules require `BD-16`. No 24/7 enterprise support promise is made. |
| REQ-047 | Implemented workflow changes require meaningful tests covering their success, exception and authorization behavior; all relevant tests must pass before completion is claimed. Unrun/failed checks and remaining work are explicit. Staff-facing workflow/policy instructions and business fallback are documented and validated with the owner/operator before reliance. |
| REQ-048 | The product must not execute harmful submitted content in its own handling/display/download behavior; invalid content cannot alter unrelated records or expose secrets. Verification covers representative harmful/malformed input and approved upload handling limits before operational intake (`BD-03`). This is not a safety guarantee for external CAD/slicer/viewer software and does not require automated geometry repair/scanning. |
| REQ-049 | The product records enough events to measure otherwise-valid submission attempts/technical failures separately from expected validation rejection, next-action/assignment coverage, status updates and observed progress checks, using Section 14 definitions. Private file contents and general customer profiling are unnecessary. |
| REQ-050 | Before real reliance, owner/operator acceptance checks cover normal work, quotation changes/rejection, failed prints/reprints and cancellation, and samples verify every accepted pilot quote's exact agreement/order links and each fulfilled/closed order's fulfillment outcome/current payment state. Investigate every reported lost-file, unauthorized-disclosure/change or integrity incident; a quiet pilot is not proof of readiness. |

## 4. Core user journeys

These journeys describe end-to-end outcomes, including incomplete cases. Policy-dependent steps remain blocked pending Section 13 decisions. Staff need not be separate people, but they act with the required permission.

| Journey | Steps and resulting behavior | Requirements |
| --- | --- | --- |
| J01 Customer submits | Establish customer access/ownership; enter approved preferences/contact/quantity; upload supported design; correct validation/upload errors; submit; receive one request identifier and visible review status. No price/printability guarantee follows. | REQ-001-003, REQ-027-029, REQ-032-033 |
| J02 Staff reviews | Administrator opens authorized request/files; checks completeness/suitability/material availability; records findings, responsible person and next action; prepares a quotation only when sufficient or takes J03/J04. | REQ-004, REQ-007-008 |
| J03 Clarification | Staff identify missing/unclear details; request waits for customer action; customer replies/replaces files; staff review reply and return to review. No response remains explicitly awaiting action; treatment follows `BD-15`. | REQ-005, REQ-007, REQ-030 |
| J04 Decline | Staff record unsuitable-work reason and customer-visible explanation; customer sees declined outcome; no quotation acceptance/order conversion occurs. | REQ-006, REQ-026 |
| J05 Issue quotation | Staff complete review and manual scope/price/terms; issue identified version; customer can inspect exact specification/files, total and applicable deadline before deciding. | REQ-008-010 |
| J06 Revise quotation | Before acceptance, issue a reviewed successor and supersede the earlier version. On an eligible active order, propose one current amendment against its current agreement and identify affected work to hold. Acceptance reassesses existing output/jobs; insufficient/unverified ready output returns to queued. Competing/stale amendments are superseded or re-reviewed/reissued; rejection/expiry retains prior agreement. | REQ-009-010, REQ-014-015, REQ-019, REQ-030 |
| J07 Customer accepts | Customer accepts their current eligible quote; initial acceptance creates one order; amendment acceptance validates active order stage and unchanged prior-agreement basis, then updates that same order. Retries return the same result; stale, competing, expired, terminal-order and other's agreements are denied. | REQ-011, REQ-013-015, REQ-033, REQ-041 |
| J08 Customer rejects | Customer rejects current eligible quote; decision is retained; no order appears; staff own a next action to review/requote/decline. For an amendment the current order/agreement remains. | REQ-007, REQ-012, REQ-015 |
| J09 Accepted quote becomes order | Initial acceptance creates one linked order and production-queue entry using agreed files/specification/quantity; staff confirm release under policy; amendment acceptance updates same order only. Uncertain completion is recoverable and visible. | REQ-013-017, REQ-041, REQ-044 |
| J10 Production | Authorized work is assigned, checked for release/holds, started and updated; paused/resumed only when physically accurate; success records usable units. Staff confirm sufficient usable output and checks before ready-for-fulfillment. | REQ-016-019, REQ-021 |
| J11 Failed print / reprint | Operator records failed attempt, any usable output and cause/next action; owner resolves reprint responsibility/cost; authorized new job links the failed attempt; additional charge/scope requires agreement; original failure remains visible to authorized staff. | REQ-014-015, REQ-019-020 |
| J12 Customer tracking | Customer opens own order and sees current public status, agreed scope, delay/action where applicable, payment summary and outcome. Cannot retrieve other customers' records or internal notes. | REQ-021-022, REQ-029, REQ-033 |
| J13 Payment recording | Administrator verifies an actual external receipt, records it, identifies duplicates/discrepancies and separately records corrections/refunds if approved; balance/status updates independently of physical progress. | REQ-036-040 |
| J14 Fulfillment | After ready confirmation, administrator records approved method/evidence/time; customer sees physical outcome; balance remains truthful even if not settled. | REQ-019, REQ-022-023, REQ-038 |
| J15 Cancellation | Customer asks or staff initiate a policy-permitted cancellation; owner records decision. If approved, confirm active work stopped, prevent new jobs, record physical outcome and separately resolve charges/refunds/balance. If declined, retain request/decision and next action without falsely stopping work. | REQ-024, REQ-026, REQ-039-040 |
| J16 Closing | Owner checks fulfilled/cancelled outcome, current payment/dispute state and approved closure rule; records closure basis. Outstanding amounts cannot disappear. Unsatisfied closure conditions leave order open with responsible next action. | REQ-007, REQ-025-026, REQ-038-040 |

## 5. Proposed state models

These are proposed product lifecycles, not implementation models. All transitions not listed below are invalid, including customer/operator attempts where an administrator is required. Guard failures leave the prior state unchanged with a reason. REQ-026/035 require retained transition history for **all five models**; no routine deletion resets history. Corrections record the original fact, correcting actor/time/reason and resulting interpretation rather than silently rewriting terminal states.

Expiry, payment timing, release, cancellation, refund, fulfillment and closure guards require the referenced owner policies. No unspecified policy is implemented as an automatic rule. Delays, holds, assignments and payment/dispute facts remain independent of order states to avoid a state for every combination.

### Print Request

States: **submitted**, **awaiting clarification**, **quoted**, **converted**, **declined**, **cancelled**. Unsubmitted form work is not a business lifecycle record; durable drafts are optional future scope.

| Valid transition | Actor | Conditions |
| --- | --- | --- |
| Initial -> submitted | Customer | REQ-001 validation and usable file succeed; ownership established; one successful attempt |
| submitted -> awaiting clarification | Administrator | Required questions recorded and next-action responsibility set |
| awaiting clarification -> submitted | Administrator | Customer response/file/specification recorded and reviewed; unanswered items retained |
| submitted -> quoted | Administrator | Sufficient review; initial quotation issued and linked |
| quoted -> submitted | Administrator | Re-review/scope change reason recorded; outstanding issued quote withdrawn/superseded first; no accepted agreement |
| quoted -> awaiting clarification | Administrator | Questions recorded; outstanding issued quote withdrawn/superseded first; no accepted agreement |
| quoted -> converted | Customer acceptance event | Current eligible initial quote accepted and exactly one linked order completed |
| submitted / awaiting clarification / quoted -> declined | Administrator | Suitability reason recorded; any issued quote withdrawn; no accepted agreement/order |
| submitted / awaiting clarification / quoted -> cancelled | Administrator | Approved cancellation policy/reason; any issued quote withdrawn; no accepted agreement/order |

Quotation rejection/expiry leaves the request **quoted** with staff next action; it does not duplicate quotation states or imply automatic closure. Converted/declined/cancelled requests have no outgoing transitions. A converted request's agreement source is retained; post-acceptance change/cancellation belongs to the existing order. Customer cancellation requests alone do not transition a request.

### Quotation

States: **draft**, **issued**, **accepted**, **rejected**, **expired**, **superseded**, **withdrawn**. Initial and amendment quotes use the same model; their order effect differs.

| Valid transition | Actor | Conditions |
| --- | --- | --- |
| Initial -> draft | Administrator | Linked request or queued/in-production/ready-order amendment identified; owner/customer unchanged; amendment cites current accepted basis |
| draft -> issued | Administrator | Review complete; fixed total/currency/terms/file references; validity policy established. Amendment order remains eligible; prior basis still current; competing issued amendment superseded; output/job reassessment prepared under BD-08 |
| issued -> accepted | Customer | Own eligible version within validity; exact decision recorded; initial order or same-order amendment result completed. Amendment: order still queued/in production/ready, only current amendment, prior basis equals current agreement, output/job reassessment applied; otherwise re-review/reissue required |
| issued -> rejected | Customer | Own current eligible version and decision recorded; no order creation |
| issued -> expired | Deadline event or administrator | A deadline was explicitly stated and reached under approved expiry rules; record event/time; no assumed deadline |
| issued -> superseded | Administrator | Replacement issued and linked; record successor and reason |
| issued -> withdrawn | Administrator | Reason recorded; customer-visible decision retained; no acceptance already won the action |

Draft contents may be edited before issue; edits do not create customer agreement. All terminal states have no outgoing transitions. An accepted quote remains **accepted** when an amendment is accepted; the order identifies both agreement history and its current accepted basis. There is no accepted -> draft/issued price edit, no rejected -> accepted resurrection, and no silent deadline extension.

### Order

States: **queued**, **in production**, **ready for fulfillment**, **fulfilled**, **cancelled**, **closed**.

| Valid transition | Actor | Conditions |
| --- | --- | --- |
| Initial -> queued | Initial acceptance event | One initial accepted quote, agreed scope/files, ownership and order links retained |
| queued -> in production | Authorized operator or administrator | An authorized job actually starts for the currently queued work; production release satisfied; no blocking hold/cancellation |
| queued -> ready for fulfillment | Administrator or expressly authorized operator | Following amendment reassessment, retained output is verified sufficient against current agreement, all checks complete and jobs terminal; no new printing required; preserve old output/job history without inventing a start |
| in production -> ready for fulfillment | Administrator or expressly authorized operator | Usable output meets current agreement; checks confirmed; all jobs terminal, including explicit cancellation of unnecessary queued jobs |
| ready for fulfillment -> queued | Customer amendment acceptance event | Eligible amendment accepted; existing output insufficient/unverified against changed agreement; no active jobs; preserve old output/job facts and record reassessment under BD-08; fresh release needed before work |
| ready for fulfillment -> in production | Administrator | Documented prefulfillment rework and authorized new job actually starts as part of transition; release/holds permit it; changed scope/price first agreed |
| ready for fulfillment -> fulfilled | Administrator | All jobs terminal; no unresolved issued amendment; approved fulfillment method/evidence/time recorded |
| queued / in production / ready for fulfillment -> cancelled | Administrator | Policy-approved cancellation; printing/paused jobs actually stopped or otherwise terminal; future work prevented; physical/financial outcome explicit |
| fulfilled / cancelled -> closed | Administrator | All jobs terminal; no pending factual correction; approved closure conditions recorded; truthful payment/dispute state retained |

Failed jobs do not themselves cancel orders or mark usable output ready. Payment events do not change physical state. No fulfilled -> cancelled and no closed -> active under this proposed model. Later financial complaints/refunds remain separately traceable. For factually erroneous fulfillment/cancellation/closure, BD-13 must define the resulting state, permitted correction transition and subsequent actions; that behavior is blocked until the approved rule is added to this table. A correction note alone authorizes neither rework nor closure. New production scope after a genuine fulfilled/cancelled/closed outcome uses a new request.

### Print Job

States: **queued**, **printing**, **paused**, **succeeded**, **failed**, **cancelled**. Pause is for a real stopped/interrupted attempt that may resume; waiting to start remains queued with a delay/hold record.

| Valid transition | Actor | Conditions |
| --- | --- | --- |
| Initial -> queued | Administrator; operator only for an expressly delegated approved reprint | Active eligible order and exact agreed files/output identified; normal job creation administrator-only; reprint approval/delegation under BD-10; ready-stage creation only for documented rework |
| queued -> printing | Authorized operator or administrator | Order queued/in production, current scope/release/holds permit work; actual start recorded. Ready-stage rework requires coordinated administrator-approved ready -> in-production transition; fulfilled/cancelled/closed forbidden |
| printing -> paused | Authorized operator or administrator | Actual interruption/pause recorded with reason and next action |
| paused -> printing | Authorized operator or administrator | Same attempt actually resumes; order queued/in production; current scope/release/holds permit it; fulfilled/cancelled/closed/ready order forbidden |
| printing / paused -> succeeded | Authorized operator or administrator | Actual completed attempt and usable quantity/check result recorded; cannot mark an uncompleted paused print successful |
| printing / paused -> failed | Authorized operator or administrator | Failed attempt/outcome/reason and any separately verified usable quantity recorded |
| queued -> cancelled | Administrator or authorized operator | Work will not start; reason/authority recorded |
| printing / paused -> cancelled | Administrator or authorized operator | Physical stopped-work confirmation and reason/authority recorded |

Succeeded/failed/cancelled attempts have no outgoing transitions. A reprint is a new queued job referencing the failed job; a failed -> printing reset is invalid. Cancelling one job is not permission to cancel the order or change the customer's price.

### Payment settlement and events

Payment has two dimensions: derived **settlement status** and a separately recorded **disputed/unresolved flag**. Refunds/adjustments are retained events/totals, not mutually exclusive statuses that hide the resulting balance. This is a record of external money activity; no transfer is initiated.

Proposed settlement labels are **unpaid**, **partially paid**, **paid**, and **overpaid**. Let A be the current agreed/approved payable amount and N the verified net recorded receipts after refunds/corrections in the same currency. Under the approved arithmetic policy: unpaid when N <= 0 and A > 0; partially paid when 0 < N < A; paid when N = A; overpaid when N > A. A zero-payable order is paid/settled with zero due, explicitly showing that no receipt occurred. Negative/unexplained amounts or currency mismatch are flagged unresolved; they are never disguised as valid settlement. `BD-14` must approve exact event/arithmetic meanings before implementation.

| Valid transition | Actor | Conditions |
| --- | --- | --- |
| Initial -> calculated status | Initial agreement/order event | Known agreed amount/currency and no assumed receipt; unknown amount yields explicit unresolved state |
| Any settlement label -> any recalculated label, including same label | Administrator records verified receipt/refund/correction, or customer accepts amount-changing amendment | Identified permitted event, approved amount/currency and arithmetic; resulting A/N satisfy new label; history retained |
| clear -> disputed/unresolved | Administrator | Specific discrepancy/context, responsible person and next action recorded |
| disputed/unresolved -> clear | Administrator | Resolution evidence/reason recorded; balance still recalculated from actual retained events |

Manual arbitrary status toggles, customer/operator verified receipts, unsupported currency mixing, or cancellation -> automatic paid are invalid. A cancelled/fulfilled/closed order may later receive a verified payment/refund/correction without rewriting its physical history; policy-dependent reopen/closure implications are not assumed. An overpayment requires reconciliation next action, not an automatic refund. A full refund can return a still-owed order to unpaid; if a cancellation agreement also reduces A, the separate approved adjustment shows why settlement differs.

## 6. Quotation agreement rules

REQ-008-015/030/041 make agreement independent of mutable request screens. The customer must be able to identify exactly what was issued and accepted: files, specifications, quantity, total/currency, terms and deadline if applicable. The accepted copy and acceptance/rejection history remain retained.

Before acceptance, replaced/withdrawn quotations are ineligible even if a customer previously opened them. After acceptance, staff cannot substitute files, price or quantity directly. Only queued/in-production/ready orders are eligible for an operational amendment. One issued amendment is current per order; acceptance requires its cited prior agreement still be current. Competing/stale proposals require supersession or re-review/reissue, never application to a different agreement basis. Acceptance reassesses output/job authorization and returns insufficient/unverified ready work to queued without rewriting old jobs. Rejection/expiry retains the earlier agreement. Unchanged work requires explicit permitted-release confirmation; changed work stays blocked. Materiality, charges, holds and reassessment rules require `BD-08`. After genuine fulfillment/cancellation/closure, new scope uses a new request; permitted financial corrections are distinct from operational amendments.

Customer acceptance cannot be entered by an operator/administrator acting as the customer. Support for externally collected agreement would require an owner-approved identity/evidence rule under `BD-01`; it is not assumed for this MVP. Version records prove what the software recorded, not the customer's legal design ownership.

## 7. File handling requirements

REQ-027-032 cover STL/3MF intake, ownership association, restricted access, replacement, failed uploads, retention and deletion. Technical storage choices and automated design interpretation are outside this document.

- Define supported format checks and transfer/file limits before intake implementation (`BD-03`). Extension alone must not misrepresent a different type as approved. Completeness/type checks are distinct from staff's manual judgment of print suitability.
- Customer submission establishes record ownership/access association, not intellectual-property transfer. Define the required permission-to-print declaration and handling of confidentiality, infringement/unsafe-work concerns under `BD-04` before operational intake. Refusal is handled through the normal decline process; legal/business policy is not invented here.
- Administrator and operator access must match approved responsibilities (`BD-05`). Assignment is a reason for necessary design access, not unrestricted administrative rights. Every quote/order/job uses explicit file versions.
- Replacement never removes a file from an accepted agreement. Authorized retention/deletion must reconcile customer requests, agreed-work evidence and recovery copies (`BD-06`). A deletion request is not a promise that all copies were already removed. Do not start work against a deleted/missing agreed design.

## 8. Authorization and audit boundaries

REQ-029/033-035/042 apply to every workflow, including exceptions. The access method remains undecided, but identity and ownership must be established before exposing private records or receiving an agreement. Viewing a record earlier does not preserve permissions after revocation. Explicit access must cover both product screens and direct record/file actions without requiring a particular implementation.

Denied operations reveal no design, other customer's contact details or internal information and make no business-state change. Customer-visible agreement/status history is a limited view; staff-only history remains restricted. Production-only users cannot issue quotes, record payments, approve cancellation/closure, grant roles or inspect all customer records. Critical actions and relevant denials retain enough attribution to investigate without copying sensitive designs/secrets into logs. Audit retention/access restrictions require `BD-05`/`BD-06`; audit history is limited to important workflow/security events.

## 9. Payment requirements

REQ-036-040 and the Payment model support receipts recorded before, during or after production, partial receipts, full settlement, refunds/approved adjustments and disputed/unresolved situations. The timing, allowed methods, deposits, release restrictions and closing conditions need owner decisions. Customer-provided claims are distinguishable from staff-verified recorded receipts.

Physical completion, payment settlement and closure are separate facts. A refund record is evidence of an external event, not an instruction to a gateway. The agreed amount cannot change because a reprint failed or an operator typed a different price. A policy-permitted financial adjustment requires a reason and traceable approval; a material price/scope amendment requires fresh agreement. No integration, accounting/invoice suite, collection automation or tax calculation is included.

## 10. Exceptions and required outcomes

| Scenario | Required handling | Requirements / decisions |
| --- | --- | --- |
| Incomplete request | Field-specific error; no falsely submitted complete request; correction/retry supported | REQ-001-003; BD-02 |
| Invalid/unsupported/corrupt file | Reject with reason; no usable-file association or guarantee of printability; allow a valid retry | REQ-027, REQ-032, REQ-048; BD-03 |
| Clarification needed | Record questions, wait visibly, retain response and review before quoting | REQ-005, REQ-007 |
| Customer never responds | No automatic agreement/order; staff responsible for explicit follow-up/disposition | REQ-007; BD-15 |
| Quote expires/superseded/withdrawn | Deny stale acceptance; retain old version; request next action stays visible | REQ-010-012; BD-07 |
| Quote rejected | Retain decision; no new order; staff choose next review/requote/decline | REQ-007, REQ-012, REQ-015 |
| Specifications change before/after agreement | Retain old versions, re-review and issue successor/amendment; no silent production substitution | REQ-014-015, REQ-030; BD-08 |
| Cancellation | Separate request/approval/stopped-work confirmation; preserve actual balance and physical outcome | REQ-024, REQ-040; BD-12-13 |
| Print failure/reprint | Retain failed attempt, usable output evidence, next action and separately linked approved reprint; no automatic customer surcharge | REQ-019-020; BD-10 |
| Production delay | Record hold/reason/responsible person and truthful public explanation; no false ready/fulfilled status | REQ-021-022; BD-08-09 |
| Payment discrepancy/refund | Flag unresolved/disputed amount and next action; retained correction/refund events; do not infer settled | REQ-037-040; BD-14 |
| Duplicate request/payment or retry | Same attempt returns same result; suspected duplicate confirmed rather than silently merged; equal legitimate events remain possible | REQ-002, REQ-011, REQ-013, REQ-037, REQ-041 |
| Unauthorized access/change | Deny without disclosure/state change, retain relevant security event; no implicit privilege escalation | REQ-029, REQ-033-035, REQ-042 |
| Acceptance interrupted/concurrent revision | One valid version/decision/order; show unresolved failure honestly and recover without duplicate order | REQ-010-013, REQ-041, REQ-044 |
| Agreed file missing/unavailable | Flag discrepancy, block affected quote/job, recover or obtain renewed agreement; never substitute a new file silently | REQ-030-032, REQ-043 |
| Fulfillment/cancellation/closure entered incorrectly | Preserve original fact and correction evidence; resulting state, transition and permitted subsequent work/closure require approved BD-13 rule before behavior can proceed; a note is insufficient | REQ-025-026, REQ-035; BD-13 |

## 11. Workflow acceptance criteria

Each criterion is evaluated with authorized roles and approved applicable policies. Policy-independent and denied-access checks may be prepared first; dependent scenarios are not considered passed using an invented default. Tests must also exercise invalid edges/guards in all state tables.

| Journey | Given / When / Then acceptance scenario |
| --- | --- |
| J01 Intake | **Given** an established customer and valid approved fields/STL or 3MF, **when** upload completes and the customer submits/retries the same attempt, **then** exactly one identified submitted request links the exact files/customer/specification. **Given** missing quantity or a failed/unsupported upload, **when** submission is attempted, **then** specific errors appear and no complete request is claimed (REQ-001-003/027/032). |
| J02 Review | **Given** a submitted request, **when** an administrator reviews it, **then** completeness/suitability/material-availability findings and next-action responsibility can be retained. **When** required checks remain insufficient, **then** quote issue is denied (REQ-004/007-008). |
| J03 Clarification | **Given** missing information, **when** staff request clarification, **then** the customer's own request shows the questions and awaiting-action state. **When** the customer replies/replaces a file and staff review it, **then** reply/file history is retained and review resumes. No reply causes no automatic agreement/order (REQ-005/007/030). |
| J04 Decline | **Given** unsuitable unaccepted work, **when** an administrator declines with a reason, **then** customer sees the explanation, history remains and acceptance/order conversion is denied (REQ-006/026). |
| J05 Issue | **Given** sufficient review and approved terms, **when** staff issue a quote, **then** customer sees unique version, exact specification/files, total/currency and applicable deadline. **When** staff change it, **then** the issued copy cannot silently change (REQ-008-010). |
| J06 Revise | **Given** an unaccepted issued quote, **when** a successor is issued, **then** the old version cannot be accepted. **Given** competing amendments A/B on the same prior basis, **when** B replaces A, **then** only B is current; if the agreement basis changes, stale drafts/issued proposals require re-review/reissue. **Given** ready work insufficient/unverified under an accepted amendment, **then** it returns to queued with old output/jobs preserved. **When** authorized reassessment later verifies retained output/checks sufficient with all jobs terminal, **then** it becomes ready directly without a fabricated job/start. Fulfilled/cancelled/closed orders deny operational amendment (REQ-010/014-015/019/030). |
| J07 Accept | **Given** a customer's valid current quote, **when** acceptance repeats or races revision/expiry, **then** one eligible result is retained with identity/version/time and one order or explicit unresolved result. **Given** competing amendments or a changed current agreement, **when** stale acceptance is attempted, **then** it is denied without altering the same order; current-basis acceptance can update only that order. Terminal-order amendment acceptance is denied (REQ-011/013-015/033/041/044). |
| J08 Reject | **Given** a customer's current quote, **when** they reject it, **then** version/identity/time are retained, no order is created and staff next action remains. Rejecting an amendment leaves the original agreement/order unchanged (REQ-007/012/015). |
| J09 Conversion | **Given** a current initial quotation, **when** acceptance completes, **then** exactly one order links request/customer/agreement/files and enters the queue. **When** completion is interrupted/retried, **then** it resolves to that same order without an orphan successful acceptance. A payment-related release guard still applies (REQ-013/016/041/044). |
| J10 Production | **Given** assigned released work in an eligible order stage, **when** operator starts/pauses/resumes/completes it, **then** actual states/output and actor/time remain. **Given** revoked authorization, failed release, blocking hold or fulfilled/cancelled/closed stage, **when** start/resume is requested, **then** it fails. Ready-stage rework starts only with the coordinated approved transition. **Given** insufficient usable output, unfinished checks or any nonterminal job, **when** readiness is requested, **then** readiness is denied. Production-only users cannot create normal jobs; only expressly delegated approved reprints are creatable (REQ-017-019/034). |
| J11 Failure/reprint | **Given** a started print that fails, **when** failure and approved retry are recorded, **then** failed history remains and a new job references that attempt on the same order. Failed units are excluded from usable output; an extra charge cannot become agreed without amendment acceptance (REQ-014/019-020). |
| J12 Track | **Given** customers A/B and private staff notes, **when** A retrieves their order, **then** its truthful public status/payment summary appears. **When** A or a production-only operator retrieves an unauthorized record/file/price, **then** contents are denied with no confidential disclosure/change (REQ-022/029/033/042). |
| J13 Record payment | **Given** known agreed amount and a verified partial receipt, **when** administrator records it, **then** receipt evidence and partially-paid balance appear. Further payment/refund/correction recalculates status while retaining earlier entries. Duplicate retries do not add receipts; customer/operator attempts and unsupported currencies are rejected/flagged unresolved (REQ-036-040). |
| J14 Fulfill | **Given** approved output, ready state, all jobs terminal and no unresolved issued amendment, **when** administrator records compliant evidence, **then** physical outcome/time/method are visible and unpaid balance remains unpaid. Missing evidence, active/residual queued jobs, pending amendment or premature fulfillment is denied; fulfilled work cannot start another job (REQ-017/019/023/038). |
| J15 Cancel | **Given** active work, **when** customer requests cancellation, **then** work is not falsely marked stopped. **When** owner approves and actual jobs are stopped/terminal under policy, **then** order is cancelled, new starts are denied and financial outcome remains explicit. A declined request retains decision and actual production state (REQ-024/026/040). |
| J16 Close | **Given** fulfilled/cancelled work, **when** administrator checks closure conditions, **then** it succeeds only with all jobs terminal, recorded physical/payment outcome and approved unresolved-amount treatment. Pending factual correction or unsatisfied conditions deny closure; no debt/history disappears and later job starts are denied. **Given** an erroneous terminal outcome without an approved BD-13 correction rule, **when** correction/rework/closure is attempted, **then** dependent actions remain blocked; an added note alone cannot reopen work (REQ-017/025-026/038-040). |

Additional release evidence covers direct unauthorized read/write/deletion, permission revocation during an open session, malicious/malformed fields/files, recovery of the sample request/order/file with correct links/access, ambiguous interrupted writes, and all invalid state transitions. REQ-042-048 apply independently of whether the pilot happens to surface an incident.

## 12. Non-functional product expectations

| Area | Observable expectation | Policy/readiness boundary |
| --- | --- | --- |
| Security and authorization | All permitted/denied role/ownership scenarios pass; zero unresolved confirmed unauthorized disclosure/change; representative harmful/malformed inputs do not execute or change unrelated data | REQ-033-035/042/048; BD-01/03/05; no security claim based only on absence of reported incidents |
| Recoverability | Demonstrate recovery of linked sample request/order/file, payment/history and permissions with no accepted loss of acknowledged submitted files/confirmed records or required associations; proposed restoration within 1 business day after recovery begins | REQ-043; restoration timing is a proposal requiring BD-16 and practical fallback agreement, distinct from data-loss acceptance; no current service claim |
| Data integrity | Retry/concurrency checks produce one order per initial agreement, preserve versions/ownership and truthful outcomes; no confirmed lost submitted file or unresolvable confirmed event in tested/pilot work | REQ-011/013/026/030/037/041/044/050; investigate all reported incidents |
| Auditability | Each critical action and relevant denial is attributable with time, identified record/action, before/after where applicable and reason/evidence; no ordinary destructive history edit; customer/operator cannot inspect unrestricted internal history | REQ-026/034-035; retention/access must be approved under BD-05/06 |
| Basic performance | Proposed 95% ordinary responses within 3 seconds under the specified 5-user representative profile; transfer duration separate | REQ-045; provisional until BD-16 sets dataset/connection and adopts or revises target |
| Availability | Proposed 99% during agreed operating hours in pilot month; documented maintenance/outages, responsible recovery and fallback | REQ-046; BD-16 approves hours, exclusions and measurement; no always-on support commitment |
| Maintainability | Relevant meaningful tests pass before change completion; updated staff workflow/policy and recovery/fallback instructions; owner/operator can demonstrate normal/exception workflow without undocumented required steps | REQ-047/050; no test failure can be relabeled as completion |

These modest provisional numerical service targets are proposed review aids, not new enterprise-scale obligations or replacements for vision metrics. Scope, operating hours and representative volume must be learned before committing to them.

## 13. Open business decisions

Each decision requires owner input. A recommended default is explicitly a proposal. Independent documentation, policy-independent acceptance examples and requirements clarification can proceed; this PRD authorizes no application implementation. The final column describes later implementation dependency, once implementation is separately authorized. Resolution must record the chosen policy and update dependent requirements/tests before reliance.

| ID / decision | Why it matters / dependent requirements | Reasonable options | Proposed default and whether dependent development may proceed |
| --- | --- | --- | --- |
| BD-01 Customer identity/access/contact and agreement evidence | Prevents wrong ownership and impersonated acceptance; REQ-001/011-013/022/028-029/033/042 | Customer account or verified limited access; required contact fields; direct customer decisions versus separately evidenced external agreement | Establish verified ownership and direct explicit customer decisions; no account technology chosen. Ownership/private-access and acceptance implementation blocked until method/evidence/contact rules agreed. |
| BD-02 Offerings/specification fields | Defines complete/valid intake and review; REQ-001/004/008/019 | Approved material/color/options list; which options required/optional; allowed quantities | Small owner-approved offering list and manual availability review; no assumed catalog. Intake validation/quote scope behavior blocked pending actual fields/options. |
| BD-03 File formats/limits/handling | Prevents unsafe/unusable intake and defines valid attempts; REQ-001/027/032/045/048-049 | STL/3MF limits by size/count; format/handling rejection rules; assistance for customers without usable files | Support both vision formats with explicit bounded limits and manual suitability review; no defensible exact size yet. Upload/handling and valid-intake measurement blocked pending limits/checks. |
| BD-04 File rights/permission/confidential work | Submission is not ownership transfer; REQ-006/027-029/048 | Permission-to-print declaration; confidentiality handling; grounds for refusal/escalation | Require owner-approved permission/handling statement without assuming legal ownership transfer. Operational intake/refusal policy blocked pending decision. |
| BD-05 Staff assignment/design access/history permissions | Sensitive designs must not be available to all staff; REQ-017/029/033-035/042 | Explicit assigned-job access; broader authorized production queue groups; who may view internal history or grant permissions | Production-only access to explicitly authorized work and necessary designs; admin grants recorded. Fine-grained permissions/access verification blocked until owner confirms responsibilities. |
| BD-06 Retention/deletion/recovery copies | Balances confidential designs, customer deletion and agreement/recovery evidence; REQ-009/026/028-031/035/043 | Retention duration by file/record category; early deletion criteria; legal/operational exceptions; recovery-copy expiry | Retain agreed references/history and grant no automatic unrestricted deletion; no indefinite file retention proposal. Retention/deletion and operational file intake blocked pending durations/conditions/recovery-copy treatment. |
| BD-07 Quotation terms/currency/precision/validity | Defines exact agreement and eligible decisions; REQ-008-012/036/038 | Owner-defined totals/currency and rounding; explicitly dated expiry versus no deadline; price/term wording and any tax presentation | One clearly stated total/currency and explicit validity if used; no tax automation or default expiry duration. Issue/expiry/money representation blocked pending adopted rules. |
| BD-08 Amendments, readiness reassessment and affected-work release | Protects current agreement and truthful production state; REQ-014-015/017/019/021/030/040 | Stop all work or only affected work; materiality/charges; how existing output and job authorization are reverified against new scope | Proposed amendment stages queued/in production/ready only; one current proposal on current basis; insufficient/unverified ready output returns to queued. Hold changed work and allow unaffected work only with explicit confirmation. Dependent amendments/reassessment/release blocked pending approved rules. |
| BD-09 Payment timing/methods/deposits and production release | Vision deliberately leaves timing flexible; REQ-016-017/036/040 | Prepayment, deposit, pay-at-fulfillment or agreed credit; permitted external methods and required evidence | No timing default supported by evidence. Record flexible verified events, but release/payment-dependent behavior blocked until policy adopted. |
| BD-10 Failure/reprint responsibility/approval | Determines retry authority and financial responsibility; REQ-017/019-020/040 | Owner approval for every reprint or defined delegated cases; business-fault versus changed customer scope treatment | Preserve failure and seek owner approval unless delegation is explicit; no automatic customer surcharge. Reprint approval/charges blocked; recording failure facts can be defined independently. |
| BD-11 Fulfillment methods/evidence | Defines truthful delivered outcome; REQ-019/023/025 | Collection, local handoff, shipment if business offers it; required confirmation/proof | Use only actually offered methods with minimum owner-approved evidence. No shipping integration promised. Fulfillment behavior blocked pending methods/evidence. |
| BD-12 Cancellation/charges/refunds and physical work | Prevents unsafe stops and invented debt/refund treatment; REQ-024/037/040 | Stage-based approval/charges; which jobs must stop; what happens to produced goods; refund authorization/evidence | Customer requests; owner decides; confirm stopped work; no assumed free cancellation/refund. Approval/refund amount behavior blocked pending policy; request recording independent. |
| BD-13 Closure, disputes and factual terminal-outcome correction | Defines closure with unpaid/disputed balances and truthful handling of an erroneously recorded fulfillment/cancellation/closure; REQ-017/023-026/038-040 | Require settlement or explicit residual responsibility; financial adjustment rules; factual correction may retain terminal state or permit specifically evidenced corrective transition, with defined resulting state and permitted actions | Preserve original facts, correction evidence and truthful balance; no automatic reopening. Owner must explicitly define each allowed correction transition/result and subsequent production/closure actions and add it to state tables. Closing and dependent correction/rework/post-close behavior blocked pending approval; a note alone grants no permission. |
| BD-14 Financial event arithmetic/reconciliation | Avoids wrong balances and unsupported adjustment/refund claims; REQ-036-040 | Receipt/refund/correction meanings; proof/approval rules; overpayment/dispute handling; rounding and reconciliation | Traceable separate events, derived balance, explicit discrepancies; no automatic refund/write-off. Financial arithmetic/correction acceptance blocked until examples/rules approved. |
| BD-15 Unanswered requests/quotes | Stops stale work without assumed expiry/cancellation; REQ-005/007/010/012 | Manual review indefinitely; owner-defined follow-up/withdrawal/decline after a stated period | Manual responsible next action; no automatic acceptance/cancellation. Basic waiting behavior can be defined; time-based disposition blocked pending policy. |
| BD-16 Operational reliability and service profile | Recovery/availability costs must fit small business while respecting no confirmed submitted-file/record loss; REQ-043/045-048 | Operating hours, maintenance/fallback/responsibility; representative dataset/connection; adopt/revise response/restoration/availability targets | Review Section 12 timing/profile proposals against actual volume/capacity; restoration time never authorizes loss of acknowledged files/confirmed records. No existing SLA claimed. Numerical service acceptance and reliance blocked until targets/profile/fallback agreed. |
| BD-17 Baseline, pilot sample and improvement threshold | Vision hypotheses/targets lack business baseline; REQ-049-050 and Section 14 | Observe current handling/inquiries; agree comparable sample/window; extend low-volume pilot; select meaningful improvement/maintenance threshold | Vision's proposed four-week window only after baseline/sample agreement; adopt no invented improvement percentage. Metric definitions can be prepared; business-value validation cannot pass before baseline/threshold decision. |

## 14. Product analytics and validation

Use the vision's measures, not engagement counts, feature count or portfolio popularity. All targets below are the **vision's proposed pilot targets**, subject to `BD-17`; the proposed initial four-week window is not a committed deadline. Record sample sizes and extend observation when volume is insufficient. Baseline business problems remain hypotheses until owner/operator observation confirms them.

| Vision measure | Definition / evidence / proposed target |
| --- | --- |
| Usable intake | Count otherwise-valid customer submission attempts within agreed fields/format/size limits and their technical success/failure outcomes. Group retries of one submission attempt consistently; separately count expected validation rejections and explain exclusions. Target at least 95% technical success; show denominator and failure reasons, not only a percentage (REQ-001-003/027/032/049; BD-03/17). |
| Work visibility | At daily close, review all open requests/orders and count records with a current next action and responsible staff member, including waiting-on-customer work. Target at least 90%; retain missing/stale-action findings. Terminal requests and closed orders are excluded; fulfilled/cancelled but unclosed orders remain open (REQ-007/049; BD-17). |
| Status accuracy | Compare each reviewed active order's recorded state/progress with operator-observed work and timestamps; count material changes recorded within one business day. Target at least 90% of active orders reflect observed progress with timely material changes; record sample/observations and discrepancies. Agree operating-day definition, what constitutes a material update and review coverage (REQ-018-022/049; BD-16/17). |
| Operational value | Before pilot, measure current handling effort and status inquiries; compare equivalent work during pilot and collect owner/operator feedback. Owner/operator set a useful improvement threshold after baseline; combine observed benefit with maintenance effort to decide whether continued use is worthwhile. No invented efficiency percentage or message-volume target (REQ-047/049; BD-17). |

Separate pre-reliance expectations from business improvement metrics:

- Every accepted pilot initial quote traces to its exact agreed version and one resulting order; accepted amendments trace to the same order and retained agreement history (REQ-009-015/050).
- Every fulfilled/closed pilot order has fulfillment or cancellation outcome, current payment state and explicit unresolved amount/dispute where applicable; closure follows approved policy (REQ-023-025/038-040/050).
- No confirmed lost submitted file, unauthorized customer/internal disclosure or unauthorized change is acceptable. Verify denied reads/writes and staff permissions, recover the sample request/order/file, and investigate every incident (REQ-029/033-035/042-044/048/050).
- Relevant meaningful tests pass, and owner/operator validate normal workflow plus quotation changes, rejection, failure/reprint and cancellation before relying on those behaviors (REQ-047/050).

This PRD supplies proposed acceptance expectations and policy questions. It is not evidence of implemented behavior, passed application tests or production readiness. Human approval of scope and resolution of dependent business decisions precede later implementation planning.
