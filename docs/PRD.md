# Cetakin Cloud MVP Product Requirements Document

**Status:** Reduced first-MVP proposal for human review. Behaviors/defaults are proposals, not approved policies or authorization to implement or operate software.

**Authority:** `AGENTS.md`, `README.md`, and `docs/VISION.md`. No framework, database, cloud provider, storage design or implementation architecture is selected. Only this PRD changes; handoffs assign future work without editing its destination.

## 1. Scope and reduction decisions

The reduced first MVP remains end-to-end: owned customer request and STL/3MF files; manual review, clarification or decline; identified quotation and explicit customer decision; one linked order; production, failed prints and reprints; own-order tracking; external payment records; fulfillment, cancellation and closure. Minimum contact details, a work list with next action/responsible staff, restricted files and important history support it. One person may hold multiple roles.

**Proposed change boundary, requiring BD-08 approval:** one current amendment quotation may change an accepted order only while queued and before **any job has ever actually started**, including subsequently failed/cancelled attempts. Issue and acceptance recheck that condition, current agreement basis and the production hold. Renewed acceptance updates the same order, retaining previous agreements. After any production start, proposed changed work/price remains a visible held exception with owner next action; original scope/files/price stay frozen. Owner-approved resolution might retain original scope or use cancellation and a separately agreed replacement request; neither is assumed policy. A note/external conversation alone cannot authorize changed work or surcharge. Reconsider this limitation before approval if it does not fit Cetakin.

Deferred machinery: amendments during/after production, partial unaffected-work continuation, competing amendment chains, amendment readiness/output-reuse loops, salvage/allocation accounting, configurable permissions administration, operator reprint creation/delegation, self-service deletion/automatic retention, automated stale-work disposition, terminal reopening workflows and numerical SLA commitments. Essential holds, same-scope quality rework, revocation, policy-compliant assisted deletion, factual-error warnings/correction evidence and safe owner resolution remain required.

This is the **final first-MVP boundary**, not an initial vertical slice. A later private-intake-and-review slice is only a proposed delivery sequence; staff authorization is required before review. It is neither a completed MVP nor production-ready. No roadmap is selected here.

Post-MVP items are uncommitted candidates requiring business evidence. All vision exclusions remain excluded: own-product storefront/catalog/checkout; automated slicing, geometry repair, previews, instant pricing/AI; printer telemetry/control/scheduling; full inventory/purchasing/suppliers/replenishment; CRM/marketing/loyalty/segmentation; integrated payments, accounting/invoicing suites/tax automation; comprehensive notifications, advanced dashboards/BI/general audit platform; tenancy, marketplaces, subscriptions, native apps and generalized manufacturing/ERP. Manual availability checks, customer status access and consequential history remain included.

## 2. Personas and permissions

Roles describe responsibilities, not departments. Ownership/access method remains BD-01. Production-only users receive no admin privileges. Customer-visible fields must be explicit; internal notes, design diagnostics, staff financial references and other customers' details remain private.

| Responsibility | Customer | Owner / administrator | Production operator only |
| --- | --- | --- | --- |
| Intake/contact | Submit/view own requests/files; answer clarification; request replacement/deletion | Review authorized requests/necessary contacts; clarify/decline | Necessary instructions/contact for authorized work only; no customer directory |
| Quotation/agreement | Own issued versions; accept/reject eligible version | Prepare/issue/replace/withdraw; inspect agreements; never impersonate customer acceptance | Agreed production specifications only; no quote prices/terms or decision action |
| Orders/designs | Own public status, agreed specification/files, outcome/payment summary | Task-authorized business records/design access | Assigned or explicitly authorized work/necessary designs only |
| Production | Own public progress/outcome, no diagnostics | Assign/create jobs; approve exceptions/readiness | Update authorized progress/failure/output; propose reprint; no job creation by default |
| Money/outcome | Own payment/refund/balance summary; request cancellation | Verify/record/correct external money; decide cancellation; fulfill/close | No prices, money records/corrections, cancellation approval, fulfillment or closure |
| Permissions/history | Own public agreement/status history | Grant/revoke authorized access; relevant internal history | Necessary production history only; no privilege grants/unrestricted history |

## 3. Classification and canonical requirements

All original IDs remain traceable. **MUST** is essential behavior/quality; **SHOULD** is useful after the first working vertical slice, with simple alternatives where sufficient; **DEFER** is outside this MVP; **MOVE** sends detail to future planning while retaining stated outcomes/gates; **MERGE** retires duplicate wording into named canonical requirements. Retired IDs are not additional active requirements. No destination file is edited.

Categories: **A** essential product; **B** production-quality guardrail; **C** unresolved business policy; **D** optional later feature; **E** engineering detail. Composite dispositions retain core invariants explicitly. Section 7 gates only dependent behavior. Every active requirement must be testable.

| Original ID | Priority / category | Canonical requirement or disposition |
| --- | --- | --- |
| REQ-001 | MUST / A | Submit established customer/contact reference, completed supported file, approved material/color/options and positive whole-number quantity. Invalid inputs/failed upload give specific errors, never complete-request success; correction/retry works. BD-01/02/03 define actual inputs. |
| REQ-002 | MUST / A | Intake returns one owned request identifier linked to exact specification/files. Same-attempt retry returns it; deliberate repeats possible. Similarity detection/automatic merging deferred. |
| REQ-003 | MERGE / A | Failure/truthful result/retry canonical in REQ-001, REQ-002 and REQ-027. Durable drafts not required. |
| REQ-004 | MUST / A | Administrator records sufficient completeness, suitability and manual material/color availability review before quotation. Insufficient review blocks issue; no analysis/checklist engine. |
| REQ-005 | MUST / A | Staff record clarification questions; customer supplies identifiable replies/replacement files; staff review before quoting. Questions/replies and actor/time stay linked; silence creates no agreement/order. |
| REQ-006 | MUST / A | Owner declines unsuitable unaccepted work with customer-visible reason. History remains; declined work cannot convert to order. |
| REQ-007 | MUST / A | Open requests/orders show next action/responsible staff in simple work list, including customer-waiting work. Missing/stale actions identifiable; sole owner may own everything. No escalation engine. |
| REQ-008 | MUST / A | Manual quote identifies customer/request, exact file/specification versions, quantity, total/currency and terms. Deadline explicit if used; BD-07 defines terms, no assumed duration. |
| REQ-009 | MUST / A | Issued quote has unique version/identity/time; contents inspectable and never silently changed. Replacement creates new version; accepted version/decision evidence immutable. |
| REQ-010 | MUST / A | One current eligible issued proposal per request/supported amendment. Replacement supersedes old version; withdrawal/rejection/stated expiry denies acceptance. Deadline honored without expiry scheduler. |
| REQ-011 | MUST / A | Customer explicitly accepts own eligible version; retain identity/version/decision/time. Repeat returns original result; replacement/withdrawal/job-start conflicts cannot accept stale/ineligible quote or add agreement/order. |
| REQ-012 | MUST / A | Own rejection retains identity/version/time; no new order, staff next action. Rejected amendment leaves prior agreement unchanged. |
| REQ-013 | MUST / A | Initial acceptance creates exactly one retrievable order linked to customer/request/agreement/files/specification. Interrupted conversion honestly unresolved then recovers same order; missing order never successful conversion. |
| REQ-014 | MUST / A; advanced part D | Amendment requires queued order/no historical job start, one current proposal/current accepted basis, hold/renewed agreement. Acceptance updates same order/retains prior agreements; obsolete queued jobs blocked until cancelled/recreated for agreed scope. Rejection/silence retains original basis. Late changes held owner exceptions; active-production amendment/readiness machinery deferred. BD-08 must approve boundary. |
| REQ-015 | MERGE / A | Rejected/unanswered-change safety/holds canonical in REQ-014/021. Partial unaffected-work continuation deferred. |
| REQ-016 | MUST / A | Manual queue carries agreed scope/quantity, action/responsible staff and separate payment state. Work starts only under BD-09 release rule; acceptance/payment alone implies neither release nor mandatory prepayment. |
| REQ-017 | MUST / A | Owner assigns/creates jobs with order/exact agreed files/specification/quantity. Operator starts only authorized released queued/in-production work without hold. Ready-stage same-scope rework needs owner approval/coordinated actual start; terminal order cannot start/resume jobs. |
| REQ-018 | MUST / A; pause state SHOULD | Operator records actual progress/interruption, completion/output, failure or confirmed stopped cancellation with actor/time/reason. Delay not fake start; terminal attempts retained. Distinct pause/resume facility optional if actual same-attempt reuse requires it. |
| REQ-019 | MUST / A; advanced part D | Verify usable quantity/checks before ready; failed output not counted; all jobs terminal, unnecessary queued jobs cancelled. Known defect immediately withdraws public readiness with warning/hold; approved same-scope rework returns to production. Salvage/allocation/amendment-output-reuse engines deferred. |
| REQ-020 | MUST / A; delegation D | Failed attempt/reason/action retained; approved reprint new linked job on same order, no automatic price/payment/outcome change. Post-failure surcharge/scope is held late-change proposal; freeze price pending separately agreed supported resolution, not pre-start amendment/note. BD-08/10 govern; operator creation/delegation deferred. |
| REQ-021 | MUST / A | Record delay/hold/reason/responsible next action; customer truthful explanation. Note cannot authorize changed work. Preserve prior promised date/term and identify proposal; extra date tooling optional. |
| REQ-022 | MUST / A | Own identifier/agreement/specification, public progress/action/outcome/payment only. Delay, factual uncertainty or unresolved money never displays completed/settled certainty. |
| REQ-023 | MUST / A | Owner records offered fulfillment method/time/evidence from verified ready/terminal jobs/no blocking error. Physical fulfillment never manufactures settlement. |
| REQ-024 | MUST / A | Customer/staff request; owner approves/declines BD-12 cancellation. Request stops no print; approved cancellation confirms stopped/terminal jobs, cancels queued work, prevents starts, records physical/financial outcome without zeroing debt. |
| REQ-025 | MUST / A | Close accurate fulfilled/cancelled work under BD-13 with outcome/terminal jobs/payment/residual responsibility. Known affected error blocks closure; rare reopening policy cannot block unrelated accurate closure. |
| REQ-026 | MUST / B; reopening D | Unauthorized/unlisted/unguarded transitions fail unchanged. Retain actor/time/original/correction reason. Erroneous outcome visibly unverified/disputed to staff/customer, evidence/owner/action/affected hold. Approved manual resolution establishes truth/safe action; note grants no reopening. Reopening tooling deferred. |
| REQ-027 | MUST / A | STL/3MF upload complete within BD-03 checks before usable association. Unsupported/misleading/incomplete/corrupt/oversized rejection with reason. Product executes no supplied content or unrelated disclosure; no geometry/printability/external-software safety guarantee. |
| REQ-028 | MUST / A | File version/submitter/request/time/name/outcome; quote/order/job exact agreed versions. Submission record association, not IP transfer; BD-04 permission needed. |
| REQ-029 | MERGE / B | Own/task-limited design access/direct file/metadata/change actions canonical in REQ-033. |
| REQ-030 | MUST / A | Replacement identified without erasing accepted references. Preacceptance material change needs review/new quote; postacceptance supported REQ-014 or hold. Missing/deleted agreed design visible, dependent quote/work blocked pending recovery or supported renewed agreement. |
| REQ-031 | MUST / C; automation D | Retention/assisted deletion actually meets BD-06 before real designs. Record request/outcome/deferral reason; permitted agreement evidence/unavailable marker retained. No indefinite promise; self-service/automatic erasure deferred. |
| REQ-032 | MERGE / A/B | Failed upload/no false association canonical REQ-001/027; missing-file hold/recovery REQ-030/043. |
| REQ-033 | MUST / B | Customer only own permitted records/files; staff task-authorized designs/records. Operator no admin/money/directory. Deny lists/details/direct changes/deletion without confidential disclosure/state change. |
| REQ-034 | MUST / B; editor D | Authorized grants/revocation retain person/permissions/actor/time/reason. Revoked access denies later actions from already-open views; no self-promotion. Configurable editor deferred. |
| REQ-035 | MUST / B; broad audit D | Attributable agreement/consequential state/production/money/correction/outcome/deletion/permission facts retained, no destructive history edit. Relevant denial evidence without secrets/design contents. Every successful read/download audit/general dashboards deferred. |
| REQ-036 | MUST / A | Owner verifies external receipt/order/amount/currency/date/available method/reference/actor/time. Customer/operator claim not verified receipt; product moves no funds. |
| REQ-037 | MUST / A; detection D | Separate receipt/refund/approved corrections with original-event reference/reason/actor/time. Same-event retry returns existing money entry; equal legitimate payments possible. Similarity detection/reconciliation tooling deferred. |
| REQ-038 | MUST / A | Agreed payable/net receipts/refunds/adjustments/balance/unpaid-partial-paid displayed. Excess/unknown/mismatched currency visibly unresolved; no silent conversion/settlement/refund. BD-07/14 approve arithmetic/examples. |
| REQ-039 | MUST / A; case management D | Discrepancy/dispute context/known amount/owner/action; resolution evidence retained alongside balance. Unknown debt not zero; case-management tooling deferred. |
| REQ-040 | MERGE / A/C | Refund/correction/balance REQ-037/038; cancellation/closure REQ-024/025; renewed agreement REQ-014. Compensation/write-off automation deferred; timing not invented. |
| REQ-041 | MUST / B; test detail E | Ownership/exact associations survive repeats/conflicts: no duplicate initial order/receipt, stale agreement, lost confirmed outcome/substitution. Visible inconsistency holds dependent action; detailed concurrency tests to future TEST_STRATEGY. |
| REQ-042 | MOVE / E; gate B | Permission-test matrix to future TEST_STRATEGY; REQ-033/034/Section 8 retain allowed/denied authorization gate. |
| REQ-043 | MUST / B; SLA D | Before reliance recover linked sample request/order/file/payment/history/permissions; practical fallback/recovery owner. Confirmed submitted-file/record loss unacceptable; numerical restoration SLA deferred, no permitted loss. |
| REQ-044 | MERGE / A/B | Truthful result/safe retry/hold canonical REQ-001/013/037/041. |
| REQ-045 | DEFER / D | Remove 3-second/5-user percentile commitment. Check normal-task responsiveness on actual profile before pilot; BD-16 useful conditions then. |
| REQ-046 | DEFER / D | Remove 99% monthly commitment; practical hours/fallback/owner REQ-043/BD-16. No enterprise monitoring/support. |
| REQ-047 | MOVE / E; gate B | Test execution detail governed by AGENTS.md/to future TEST_STRATEGY. Section 8 retains passing tests/staff instructions/fallback validation. |
| REQ-048 | MERGE / B/E | Safe handling/confidentiality REQ-027/033; detailed harmful-input verification future TEST_STRATEGY. |
| REQ-049 | SHOULD / B | Lightweight intake/failure evidence; manual tallies/review can measure actions/progress/effort. Section 9 vision definitions mandatory, no telemetry/dashboard build. |
| REQ-050 | MOVE / E; gate B | Pilot/test checklist future TEST_STRATEGY/pilot planning. Sections 8/9 retain owner validation/link-outcome-payment checks/incidents. |

## 4. Proposed product lifecycles

All unlisted transitions/failed guards invalid and unchanged; retain history REQ-026/035. Factual warnings override misleading public certainty/hold affected actions. Correction preserves originals, grants no unapproved transition. Product models only; detailed invariants/tests future DOMAIN/TEST_STRATEGY.

### Print Request

States: submitted, awaiting clarification, quoted, converted, declined, cancelled. Unsubmitted forms need not persist. Rejected/expired quote leaves quoted request/next action; no silent conversion/cancellation.

| Transition | Actor | Conditions |
| --- | --- | --- |
| Initial -> submitted | Customer | Ownership/complete valid inputs/usable file |
| submitted -> awaiting clarification | Administrator | Questions/next-action responsibility |
| awaiting clarification -> submitted | Administrator | Reviewed response sufficiently answers questions |
| submitted -> quoted | Administrator | Sufficient review/identified issued quote |
| quoted -> submitted / awaiting clarification | Administrator | Re-review/questions; pending quote withdrawn/superseded; no accepted agreement |
| quoted -> converted | Customer acceptance event | Eligible initial agreement/one completed order |
| submitted / awaiting clarification / quoted -> declined / cancelled | Administrator | Reasoned decline or approved cancellation; pending quote withdrawn/no accepted order |

Converted/declined/cancelled terminal; accepted-order change/cancellation uses order rules.

### Quotation

States: draft, issued, accepted, rejected, expired, superseded, withdrawn. Draft editable, issued fixed; no default duration/scheduler.

| Transition | Actor | Conditions |
| --- | --- | --- |
| Initial -> draft | Administrator | Request or queued order/no historical job start |
| draft -> issued | Administrator | Reviewed exact scope/files/total/terms; one current proposal; amendment rechecks never-started/current-basis/hold |
| issued -> accepted | Customer | Own current version/within stated deadline; initial one-order or same-order amendment. For amendments, recheck no historical start/current-basis/hold, including conflicts |
| issued -> rejected | Customer | Own eligible version/retained decision/no new order |
| issued -> expired | Administrator or deadline eligibility event | Stated deadline reached; eligibility denied even before delayed status update |
| issued -> superseded / withdrawn | Administrator | Linked successor or withdrawal reason before acceptance |

Terminal versions never reopen; retry recovers existing acceptance. Original accepted version retained after supported amendment.

### Order

States: queued, in production, ready for fulfillment, fulfilled, cancelled, closed. Money/delays/uncertainty separate facts.

| Transition | Actor | Conditions |
| --- | --- | --- |
| Initial -> queued | Initial acceptance event | One agreement/order/exact scope/files |
| queued -> in production | Authorized operator / administrator | Actual authorized start/release/no hold-pending change-error |
| in production -> ready for fulfillment | Administrator | Verified usable quantity/checks/all jobs terminal |
| ready for fulfillment -> in production | Administrator approving actual start | Same-agreement quality rework/reprint; readiness truthfully withdrawn; release/hold checks; no changed-price/scope authority |
| ready for fulfillment -> fulfilled | Administrator | Approved method/time/evidence/terminal jobs/no affected error |
| queued / in production / ready for fulfillment -> cancelled | Administrator | Approved cancellation/actual stop/terminal jobs/queued work cancelled/physical-financial outcome |
| fulfilled / cancelled -> closed | Administrator | Accurate adopted outcome/payment/residual responsibility/no affected error |

Pre-start amendment stays queued, no output-reuse loop. Defective ready output immediately shows not-ready/review warning and holds fulfillment until approved rework/resolution. Terminal orders cannot start jobs; later money events traceable separately. Generic reopening deferred; supported resolution never directly edits agreement.

### Print Job

States: queued, in progress, succeeded, failed, cancelled. Interruption/hold notes truthful; separate paused-state facility optional.

| Transition | Actor | Conditions |
| --- | --- | --- |
| Initial -> queued | Administrator | Authorized order/exact scope/files/quantity; reprint approved |
| queued -> in progress | Authorized operator / administrator | Actual released start on queued/in-production work or coordinated same-scope ready rework/no hold-terminal order |
| in progress -> succeeded / failed | Authorized operator / administrator | Actual completion/usable quantity/checks or failure/reason/action; failed quantity not usable |
| queued -> cancelled | Administrator / authorized operator | Will not start/authorized reason |
| in progress -> cancelled | Administrator / authorized operator | Actual stop confirmation/reason |

Terminal attempts cannot restart; reprint new linked job. Job cancellation not order cancellation/charge authority.

### Payment record

Derived unpaid/partial/paid summary uses agreed/approved payable and verified same-currency receipts/refunds/corrections. Excess visibly unresolved/overpaid; dispute separate from settlement. Zero payable shows no payment due, no invented receipt. BD-14 approves precision/examples/events.

| Change | Actor | Conditions |
| --- | --- | --- |
| Initial -> summary | Initial agreement/order event | Known total/currency/no assumed receipt |
| Any summary -> recalculated summary | Administrator verified event or supported customer-agreed payable amendment | Approved amount/currency/event, retained correction history; no arbitrary toggle |
| clear -> unresolved/disputed | Administrator | Context/known-or-unknown amount/owner/action |
| unresolved/disputed -> clear | Administrator | Resolution evidence; retained events still determine balance |

Customer/operator verified receipt, erased correction, mixed-currency settlement, cancellation -> automatically paid and unsupported price edits invalid. Refund moves no funds; approved money events after fulfillment/closure do not rewrite physical history.

## 5. Journeys and acceptance criteria

One table replaces duplicate journey/acceptance sections. Apply adopted policies; unauthorized cases must deny without disclosure. All journeys MVP subject to limited J06 change boundary.

| Journey | Testable end-to-end outcome |
| --- | --- |
| J01 Submit | Owned/valid STL or 3MF/preferences -> upload/submit/retry -> one owned request/exact files/specification. Invalid/failed input specific error/no false success (REQ-001/002/027/028). |
| J02 Review | Submitted work -> administrator findings/action -> sufficient review permits quote, insufficient blocks (REQ-004/007/008). |
| J03 Clarify | Staff question/customer response/version replacement/review -> linked history/review resumes; silence no agreement/disposition (REQ-005/007/030). |
| J04 Decline | Unsuitable unaccepted work -> owner reason -> customer explanation/history, no conversion (REQ-006/026). |
| J05 Issue | Sufficient review/terms -> identified manual quote/exact scope/files/quantity/total/terms, no silent issue edit (REQ-008/009). |
| J06 Revise | Unaccepted replacement supersedes old. Never-started queued amendment -> current-basis renewed agreement/same order, obsolete work held until corrected release. Historical start including failed/cancelled -> held late proposal/action, no edit. Issue/accept/start conflict cannot bypass boundary (REQ-010/014/021/030/041). |
| J07 Accept | Own eligible acceptance/retry -> one valid identified result; stale/expired/other-owner denied. Amendment rechecks historical no-start/current-basis/hold, no extra order (REQ-011/013/014/041). |
| J08 Reject | Own decision/version/time -> no new order/next action; amendment original scope/price/files unchanged (REQ-012/014). |
| J09 Convert | One order/exact associations; interruption unresolved then same-order recovery. Release not inferred (REQ-013/016/041). |
| J10 Produce | Authorized released actual work/output; revocation/hold/terminal denies start; insufficient output/checks/nonterminal jobs denies ready. Defective ready output instantly not-ready/hold; approved same-scope linked rework, no surcharge (REQ-017/018/019/034). |
| J11 Fail/reprint | Failed attempt retained; approved linked retry preserves price. Post-failure proposed surcharge/scope held until separately agreed supported owner resolution, never pre-start amendment/note (REQ-019/020/014). |
| J12 Track | A own public truth including uncertainty; B's records/files/internal notes denied; operator financial/admin denied (REQ-021/022/033). |
| J13 Pay record | Verified external receipt/refund/correction retains originals/recalculates separately from print. Retry no extra money; excess/unknown/currency mismatch unresolved, customer/operator cannot verify receipt (REQ-036/037/038/039). |
| J14 Fulfill | Verified ready/terminal jobs/evidence -> physical fulfillment, unpaid stays unpaid. Error/active work denied (REQ-019/023/038). |
| J15 Cancel | Request stops no print; owner decision/actual stop/queued cancel -> actual outcome/payment uncertainty. Decline retains actual progress/decision (REQ-024/035/038). |
| J16 Close/correct | Accurate fulfilled/cancelled/adopted rule -> payment/residual retained closure. Error warning/affected hold until safe owner evidence, no note reopening/unrelated closure block (REQ-025/026/035/039). |

## 6. Exceptions

Visible truth/responsible next action suffice; no separate case-management products.

| Scenario | Minimum boundary |
| --- | --- |
| Incomplete request/unsupported-corrupt file/failed upload | Explain/retry, no complete request/usable association |
| Clarification/no response | Questions/action, no auto agreement/cancel/decline |
| Quote rejected/expired/superseded | Version/decision retained, stale denied/action |
| Scope/file/price changes | Frozen agreement/pre-start amendment or held late exception; no silent substitution/surcharge |
| Cancellation/failure/reprint/delay | Separate actual physical facts/decision/money; no manufactured success/stop |
| Money discrepancy/refund/correction/duplicates | Retain original/approved resolution; unknown not zero, retry not new money/order, equal legitimate events possible |
| Unauthorized access/change | No disclosure/state change, relevant investigation evidence |
| Missing agreed file/interrupted conflict | Unresolved warning/hold/exact recovery or supported agreement |
| Incorrect outcome | Public uncertainty, correcting evidence/owner safe action; no silent rewrite/arbitrary reopening |

## 7. Business decisions and stage gates

All 17 IDs remain **C: policy**, BD-16 also detail **E**, optional portions **D**. Priorities per Section 3. No general rules engine.

**BLOCKING-NOW** before first separately authorized customer-only private-intake implementation, not documentation/domain. **BLOCKING-BEFORE-FEATURE** gates named behavior/real reliance; unrelated/synthetic work may proceed if later authorized. BD-05 before **first staff access/review**, even first slice. **CAN-DEFER** no product-development prerequisite. Defaults require adoption; minimum policy, not every hypothetical, required.

| ID / priority / timing | Decision / why / dependencies | Options, minimum proposal and proceed boundary |
| --- | --- | --- |
| BD-01 MUST / BLOCKING-NOW | Ownership/private access/contact/decision identity; REQ-001/011/033 | Verified access or account/minimum contacts/direct decision proposed. Before private intake; offline support defer, no technology. |
| BD-02 MUST / BLOCKING-NOW | Actual inputs/offering/quantity; REQ-001/004/008 | Small actual material/color/options/manual availability; before intake, no full catalog. |
| BD-03 MUST / BLOCKING-NOW | STL/3MF count/size/checks; REQ-001/027 | Bounded limits; no exact size default evidence. Before upload, assistance deferred. |
| BD-04 MUST / BLOCKING-BEFORE-FEATURE: real intake/printing | Permission/confidentiality/refusal owner; REQ-006/027/028 | Approved permission/handling without IP transfer; before real designs, synthetic independent, no legal engine. |
| BD-05 MUST / BLOCKING-BEFORE-FEATURE: first staff access/review | Designs/assignment/admin authority; REQ-004/017/033/034 | Fixed responsibilities/task-limited design access; before staff even first slice, customer ownership BD-01. Custom editor/groups/delegation defer. |
| BD-06 MUST / BLOCKING-BEFORE-FEATURE: real retention/deletion | Duration/removal/evidence/recovery copies; REQ-028/030/031/043 | Category duration/actual assisted deletion, no indefinite default. Before real files, synthetic independent; automation/self-service defer. |
| BD-07 MUST / BLOCKING-BEFORE-FEATURE: quotes/finances | Total/currency/precision/terms/validity; REQ-008/010/036/038 | One stated total/currency/deadline only if offered; no assumed duration, stated deadline always honored. Automated expiry/conversion/tax engine excluded/deferred. |
| BD-08 MUST / BLOCKING-BEFORE-FEATURE: accepted changes | Never-started limitation/hold/late resolution fit; REQ-014/020/021/030 | Adopt limitation or revise scope. Retain original or approve cancellation/separate agreement as options, no default. Change feature gated not intake; live engine defer. |
| BD-09 MUST / BLOCKING-BEFORE-FEATURE: release/payments | Deposit/timing/method/proof/release; REQ-016/017/036 | Prepay/deposit/handoff/credit owner chooses, no default. Before dependent behavior, no engine. |
| BD-10 MUST / BLOCKING-BEFORE-FEATURE: reprints | Cause/approval/cost/retry; REQ-017/019/020 | Owner creates approved retry/no auto surcharge; failure recording independent; delegation/salvage defer. |
| BD-11 MUST / BLOCKING-BEFORE-FEATURE: fulfillment | Offered method/proof; REQ-023/025 | Collection/handoff/shipment if offered/minimum proof; before fulfillment, no integration. |
| BD-12 MUST / BLOCKING-BEFORE-FEATURE: cancellation/refund | Authority/stop/output/amount; REQ-024/037/038 | Owner decision/actual confirmation, no free cancel/refund promise. Minimum manual process; request independent, tariff engine defer. |
| BD-13 MUST / BLOCKING-BEFORE-FEATURE: closure/error handling | Outcome/payment/residual/correction; REQ-025/026/035/039 | Settlement or named residual owner chooses. Warning/evidence/affected hold/safe manual resolution before operational use; rare reopening CAN-DEFER, unrelated closure unaffected. |
| BD-14 MUST / BLOCKING-BEFORE-FEATURE: money records | Currency/arithmetic/proof/excess; REQ-036/037/038/039 | Minimum receipt/refund/correction examples/precision/evidence; retained events/derived balance. No reconciliation/write-off automation. |
| BD-15 DEFER / CAN-DEFER | Automated age/disposition; REQ-007/010 | Manual follow-up/withdraw/decline enough; no silence -> agreement/cancel; automation no prerequisite. |
| BD-16 MOVE / BLOCKING-BEFORE-FEATURE: real reliance | Recovery/fallback/owner/hours/profile; REQ-043/Section 8 | Future readiness/TEST_STRATEGY; minimum recovery/fallback before reliance. Numeric SLA CAN-DEFER; domain/synthetic intake unblocked. |
| BD-17 SHOULD / BLOCKING-BEFORE-FEATURE: pilot/value claim | Baseline/sample/window/threshold; Section 9 | Baseline before pilot comparison; useful threshold after baseline; four weeks only if volume supports, extend otherwise. CAN-DEFER development; no benefit claim before evidence. |

## 8. Quality and real-reliance gates

Reduction retains correctness, authorization, security, recovery/testing. Before reliance:

- Meaningful implemented-feature tests pass; failed/unrun checks/remaining work explicit. Owner/operator validate normal work, quotation change/rejection, failure/reprint/cancellation before using them.
- Verify allowed/denied customer/staff actions, direct records/files and revocation. No confirmed unauthorized disclosure/change or lost submitted file acceptable; investigate incidents. Check representative malformed/harmful inputs safely handled by product.
- Recover linked sample request/order/file/payment/history/permissions; documented recovery owner/fallback. No numeric target permits acknowledged loss.
- Every accepted quote exact version/order and fulfilled/closed outcome/payment/residual truth verified; repeats/conflicts preserve confirmed associations/outcomes.
- Staff follow minimum policy/workflow/error/fallback instructions. Check normal request/quote/status responsiveness and staffed-hours access on actual profile before pilot; BD-16 useful acceptance conditions. No 3-second, 99% or 24/7 promise.

Detailed permission matrices/input-concurrency permutations/recovery execution/checklists future TEST_STRATEGY. Mandatory quality outcomes retained; moving specification proves neither authored detail nor passed behavior.

## 9. Validation and later-planning handoffs

Vision's proposed measures/BD-17/sufficient sample, baseline hypotheses and pre-pilot comparison baseline. Quiet pilot no security/reliability proof.

| Vision measure | Evidence / proposed target |
| --- | --- |
| Usable intake | At least 95% otherwise-valid attempts within agreed input/format/size succeed; attempts/technical failures separate from expected validation rejection, consistent retry grouping/denominator. |
| Work visibility | At least 90% open requests/orders at daily close current action/responsible staff, customer waiting included; terminal requests/closed orders excluded, unclosed open. |
| Status accuracy | At least 90% active orders reflect observed progress/material changes within one business day; operator observations/timestamps/business-day review definitions. |
| Operational value | Baseline effort/status inquiries vs comparable work/feedback, useful threshold after baseline, maintenance effort in continued-use decision; no invented efficiency/vanity target. |

Manual tally/review sufficient, no analytics build. Pilot accepted agreement exact version/order; fulfilled/closed physical/payment truth; passing checks/incidents separate vision expectations.

Future handoffs **without edits here**:

| Destination | Later work |
| --- | --- |
| DOMAIN.md | Approved vocabulary/lifecycles/association invariants; unresolved policies hypotheses. |
| ARCHITECTURE.md | Implementation approaches after domain understanding; none selected here. |
| TEST_STRATEGY.md | Moved REQ-042/047/050, REQ-041/048 test detail, recovery/fallback/profile/pilot evidence. |
| ROADMAP.md | Sequence reduced end-to-end MVP/optional intake-review slice; deferrals uncommitted. |

Reviewable proposal, not implementation/test/readiness evidence. Domain/document analysis can proceed with unanswered policies; dependent implementation/reliance requires relevant decisions and later authorization.
