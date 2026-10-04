# Cetakin Cloud Product Vision

Cetakin Cloud will support the daily operations of Cetakin, a small business providing custom 3D printing services that may also sell its own printed products. It will also demonstrate production-oriented software engineering through real business use, meaningful tests, secure access, and reliable operation.

The initial product centers on one complete custom-print workflow. The operational areas listed during discovery are candidates, not a commitment to build every area in the MVP. This vision sets product direction; detailed requirements and technical decisions belong to later planning stages.

## 1. Business problem

A custom print moves through several handoffs: customer specifications and files, review, quotation, agreement, production, payment, and completion. Cetakin needs a dependable way to keep these connected and show what needs attention next.

The problems to validate are missing or ambiguous specifications, uncertainty about which quotation was accepted, disconnected production updates, repeated customer status questions, and incomplete payment records. These are discovery hypotheses; the current tools, volumes, and frequency of these problems have not yet been established.

The product must save enough operational effort to justify maintaining it. Portfolio value alone is not a reason to introduce features the business does not need.

## 2. Target users

- **Business owner or administrator:** reviews requests, issues quotations, manages orders, records payments, and resolves exceptions.
- **Production operator:** identifies the next work to perform, records printing progress and problems, and makes completion visible.
- **Customer:** submits a request and print file, supplies printing preferences, accepts or rejects a quotation, and tracks their order.

One person may perform both business and production responsibilities. The MVP should support a small operation without assuming separate departments or a dedicated support team.

## 3. Product value proposition

For Cetakin, a shared operational record connects each customer's request, agreed quotation, production work, payment record, and final outcome. For customers, clear agreement and status visibility reduce uncertainty about what they ordered and what happens next.

The engineering portfolio should demonstrate a working, supportable business workflow with evidence of correctness and authorization. Feature count and architectural complexity are not success measures.

## 4. Primary workflows

### Custom printing request to completion

1. A customer submits a printing request with an STL or 3MF file, material, color, quantity, and printing options.
2. Cetakin reviews feasibility, file suitability, specification completeness, and material availability. Staff request clarification or decline unsuitable work when necessary.
3. Cetakin prepares and issues a quotation manually. The customer accepts or rejects a clearly identified quotation; unanswered quotations remain unresolved rather than becoming orders automatically.
4. An accepted quotation becomes a traceable order using the agreed specifications and quotation. A material change to price or scope requires renewed customer agreement.
5. The order enters the production queue. An operator manages the printing work and records progress, delays, failures, or reprints so the order's status remains truthful.
6. The customer can see the current status of their own order. Internal information and other customers' records remain private.
7. Staff record payments and any relevant adjustments. Payment may occur before, during, or after production according to a business policy that still needs to be agreed.
8. Staff record fulfillment and the final outcome. Physical completion and payment settlement must remain distinguishable; the conditions for closing an order will be defined during requirements planning.

### Supporting operational work

Customer contact information, retained request files, and a lightweight history of important agreements, production updates, and payment changes support the primary workflow. Basic visibility into outstanding work is sufficient initially. Material and color availability can be confirmed during review without a full inventory management system.

Exceptions such as rejection, cancellation, changed specifications, failed prints, and payment discrepancies need a visible outcome and responsible person. Their detailed rules are unresolved; they must not be hidden by an idealized success-only workflow.

## 5. Product goals

- Make the custom-print lifecycle usable from request through final outcome without disconnected records.
- Make the agreed quotation and specifications clear to both Cetakin and the customer.
- Give staff an accurate view of current work and the next action, including production exceptions.
- Let customers track their own orders while protecting customer and business information.
- Preserve enough history to resolve agreement, status, and payment questions.
- Keep daily operation and maintenance affordable for a small business.
- Demonstrate production-oriented engineering through tested behavior, authorization, and dependable operation once implementation is authorized.

## 6. Measurable success criteria

These are proposed pilot targets, not established business performance or launch commitments. Confirm them against Cetakin's volume and current process before adopting them. An initial four-week review window is a proposal; agree a useful sample after learning actual volume and extend observation when evidence is insufficient. Low volume limits conclusions rather than indicating product failure.

| Measure | Proposed target and evidence |
| --- | --- |
| Usable intake | At least 95% of otherwise valid attempted submissions within agreed file format and size limits succeed; record attempts and technical failures. Track expected validation rejections separately so they do not count as technical failures. |
| Work visibility | At least 90% of open requests and orders reviewed at daily close have a current next action and responsible staff member. |
| Status accuracy | At least 90% of active orders reflect observed progress, with material changes recorded within one business day; compare records with operator reviews. |
| Operational value | Measure current handling effort and status inquiries before the pilot, then compare equivalent work and collect owner/operator feedback. The owner/operator agree a useful improvement threshold after the baseline is known and use it, together with ongoing maintenance effort, to decide whether continued use is worthwhile. |

Separate acceptance expectations apply to implemented features before real business reliance:

- Every accepted pilot quotation traces to its agreed version and resulting order.
- Every fulfilled or closed pilot order records its fulfillment outcome and current payment state; unresolved amounts or disputes are explicit and closing rules follow the agreed business policy.
- No confirmed lost submitted files, unauthorized disclosure of customer or internal information, or unauthorized changes is acceptable. Verify customer access boundaries and staff permissions against agreed responsibilities, including denied access and changes, and recovery of a sample request, order, and file; investigate every reported incident.
- Meaningful tests pass, and the owner/operator validate the normal workflow and quotation changes, rejection, failed prints/reprints, and cancellation before relying on those behaviors.

A quiet pilot cannot prove security or reliability. These are future checks, not evidence already achieved. No production-readiness claim follows from writing this vision or meeting a small sample of business metrics.

## 7. Assumptions and questions to validate

- Custom printing is the first operational priority; selling Cetakin's own products can wait.
- Staff can review files and prepare quotations manually; no automated slicing or instant price estimate is required initially.
- The business can keep production updates current without automated printer integration.
- Customers can provide STL or 3MF files and enough specifications for review. File limits, handling unsuitable files, and assistance for customers without printable files remain open.
- Customer access and order ownership must be established, but the account or access method is undecided.
- Customer status tracking is desired scope; its adoption and usefulness still need validation with actual customers before adding a broader customer portal.
- Payment records are sufficient initially. Accepted payment methods, deposits, production-release rules, refunds, and order-closing conditions need business decisions.
- Available materials, supported printing options, turnaround expectations, fulfillment methods, and cancellation/reprint policies still need confirmation.
- File ownership, customer permission to print, retention expectations, and staff access rules need agreement.
- Current tools, request volume, staffing, maintenance capacity, and baseline effort must be understood before promising efficiency or scale.

## 8. Risks

- **Excess scope:** building CRM, inventory, commerce, and analytics together may prevent delivery of a usable core workflow. Validate the custom-print lifecycle before expanding.
- **Low adoption or stale records:** extra data entry may create inaccurate status information. Pilot with the people doing the work and check whether updates fit their routine.
- **Misquoted or unsuitable work:** manual review may miss printing constraints or cost. Preserve explicit review and agreement; do not present a file upload as a guarantee of printability or price.
- **Unresolved operational policies:** deposits, changes, failed prints, and cancellations can cause disputes. Agree these policies before implementing dependent behavior.
- **Sensitive or unsafe uploads:** customer designs may be confidential, unauthorized to reproduce, or unsafe to process. Define access, handling, retention, and recovery expectations before file intake is used operationally.
- **Portfolio priorities overriding business needs:** impressive features can increase maintenance burden. Judge additions by demonstrated operational benefit and realistic ownership.
- **Reliability and limited evidence:** record loss or inaccessible order information can interrupt work, and a small pilot may conceal defects. Validate recovery and maintain a practical business fallback before depending on the product.

## 9. Explicitly out of scope for the MVP

- Product storefront, catalog checkout, and management of Cetakin's own-product sales.
- Automated slicing, geometry repair, 3D previews, instant pricing, and AI recommendations.
- Printer telemetry, remote printer control, and automated print-farm scheduling.
- Full filament stock accounting, purchasing, suppliers, predicted consumption, and replenishment automation.
- Full CRM, marketing campaigns, loyalty programs, and customer segmentation.
- Integrated payment processing, invoicing/accounting suites, and tax automation; staff payment records remain in scope.
- Comprehensive notification automation, advanced dashboards, business intelligence, and a general-purpose audit platform. Customer status access and important workflow history remain in scope.
- Multi-business tenancy, marketplaces, subscriptions, native mobile apps, and generalized manufacturing or ERP features.

These exclusions limit initial scope; they do not promise later delivery. Reconsider them only when business evidence shows that the core workflow is insufficient.
