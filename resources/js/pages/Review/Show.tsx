import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import FormFeedback from '../../components/Access/FormFeedback';
import Logout from '../../components/Access/Logout';
import IntakeNotice from '../Intake/IntakeNotice';
import { fileSize } from '../Intake/types';
import {
    reviewLabels,
    workLabels,
    type Actor,
    type ReviewDetail,
    type WorkDisposition,
} from './types';
import '../../../css/access.css';
import './review.css';

type ResponsibleOption = { id: number; name: string };

function WorkFields({
    prefix,
    data,
    errors,
    responsibleOptions,
    onChange,
}: {
    prefix: string;
    data: {
        responsible_user_id: string;
        next_action: string;
        work_disposition: WorkDisposition;
    };
    errors: Record<string, string | undefined>;
    responsibleOptions: ResponsibleOption[];
    onChange: (key: string, value: string) => void;
}) {
    return (
        <>
            <div className="access-field">
                <label htmlFor={`${prefix}-responsible`}>
                    Responsible Owner ({prefix})
                </label>
                <select
                    id={`${prefix}-responsible`}
                    value={data.responsible_user_id}
                    onChange={(event) =>
                        onChange('responsible_user_id', event.target.value)
                    }
                    aria-invalid={errors.responsible_user_id ? true : undefined}
                    aria-describedby={
                        errors.responsible_user_id
                            ? `${prefix}-responsible-error`
                            : undefined
                    }
                >
                    <option value="">Unassigned — coverage required</option>
                    {responsibleOptions.map((option) => (
                        <option key={option.id} value={option.id}>
                            {option.name}
                        </option>
                    ))}
                </select>
                {errors.responsible_user_id && (
                    <p
                        id={`${prefix}-responsible-error`}
                        className="access-field-error"
                    >
                        {errors.responsible_user_id}
                    </p>
                )}
            </div>
            <div className="access-field">
                <label htmlFor={`${prefix}-next-action`}>
                    Next action ({prefix})
                </label>
                <textarea
                    id={`${prefix}-next-action`}
                    value={data.next_action}
                    onChange={(event) =>
                        onChange('next_action', event.target.value)
                    }
                    rows={3}
                    maxLength={500}
                    required
                    aria-invalid={errors.next_action ? true : undefined}
                    aria-describedby={
                        errors.next_action
                            ? `${prefix}-next-action-error`
                            : undefined
                    }
                />
                {errors.next_action && (
                    <p
                        id={`${prefix}-next-action-error`}
                        className="access-field-error"
                    >
                        {errors.next_action}
                    </p>
                )}
            </div>
            <div className="access-field">
                <label htmlFor={`${prefix}-disposition`}>
                    Work disposition ({prefix})
                </label>
                <select
                    id={`${prefix}-disposition`}
                    value={data.work_disposition}
                    onChange={(event) =>
                        onChange('work_disposition', event.target.value)
                    }
                >
                    <option value="ACTIONABLE">Actionable</option>
                    <option value="WAITING">Waiting</option>
                    <option value="BLOCKED">Blocked</option>
                </select>
            </div>
        </>
    );
}

function DecisionForms({
    request,
    responsibleOptions,
}: {
    request: ReviewDetail;
    responsibleOptions: ResponsibleOption[];
}) {
    const currentResponsible = request.responsible?.current
        ? String(request.responsible.id)
        : '';
    const disposition =
        request.workDisposition === 'TERMINAL'
            ? 'ACTIONABLE'
            : request.workDisposition;
    const workDefaults = {
        responsible_user_id: currentResponsible,
        next_action: request.nextAction ?? '',
        work_disposition: disposition as WorkDisposition,
    };
    const review = useForm({
        complete: false,
        material_available: false,
        feasible: false,
        sufficient: false,
        findings: '',
        ...workDefaults,
    });
    const assignment = useForm({ ...workDefaults, reason: '' });
    const decline = useForm({ reason: '', customer_reason: '' });
    const [failure, setFailure] = useState('');
    const [needsReload, setNeedsReload] = useState(false);
    const pending =
        review.processing || assignment.processing || decline.processing;
    const locked = pending || needsReload;
    const errors = {
        ...review.errors,
        ...assignment.errors,
        ...decline.errors,
    };

    function beginChange() {
        setFailure('');
        review.clearErrors();
        assignment.clearErrors();
        decline.clearErrors();
    }

    function onHttpException({ status }: { status: number }) {
        setNeedsReload(true);
        setFailure(
            status === 401 || status === 419
                ? 'Your session is no longer valid. Sign in again and reload this request before acting.'
                : status === 403 || status === 404
                  ? 'Current responsibility or request assignment no longer permits this action. Reload before acting; the open page grants no continuing access.'
                  : status === 409
                    ? 'This request or its review basis changed. Reload and examine the current record before recording another decision.'
                    : 'The result was not confirmed. Reload the current request and retained history before retrying.',
        );
        return false;
    }
    function onNetworkError() {
        setNeedsReload(true);
        setFailure(
            'The result is unknown because the connection failed. Reload the current request and retained history before retrying; do not assume the decision succeeded or failed.',
        );
        return false;
    }
    const options = {
        preserveScroll: true,
        onHttpException,
        onNetworkError,
    };

    return (
        <>
            <FormFeedback errors={errors} failure={failure} />
            {pending && (
                <p role="status">
                    Waiting for the server to confirm the recorded change.
                </p>
            )}
            <section aria-labelledby="assignment-heading">
                <h2 id="assignment-heading">
                    Responsible coverage / next action
                </h2>
                <p>
                    Only a current Owner may be responsible for this review.
                    Assignment does not promote a User or grant production
                    authority. Deliberately assign the review to yourself before
                    examining design files or recording decisions.
                </p>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        beginChange();
                        assignment.transform((data) => ({
                            ...data,
                            revision: request.revision,
                            responsible_user_id: data.responsible_user_id
                                ? Number(data.responsible_user_id)
                                : null,
                        }));
                        assignment.post(request.assignmentUrl, options);
                    }}
                    aria-busy={assignment.processing}
                >
                    <fieldset disabled={locked}>
                        <legend>Record responsible work</legend>
                        <WorkFields
                            prefix="assignment"
                            data={assignment.data}
                            errors={assignment.errors}
                            responsibleOptions={responsibleOptions}
                            onChange={(key, value) =>
                                assignment.setData(
                                    key as keyof typeof workDefaults,
                                    value,
                                )
                            }
                        />
                        <div className="access-field">
                            <label htmlFor="assignment-reason">
                                Assignment / reassignment reason
                            </label>
                            <textarea
                                id="assignment-reason"
                                value={assignment.data.reason}
                                onChange={(event) =>
                                    assignment.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                                maxLength={1000}
                                required
                                aria-invalid={
                                    assignment.errors.reason ? true : undefined
                                }
                            />
                        </div>
                        <button type="submit">
                            {assignment.processing
                                ? 'Recording responsible work…'
                                : 'Record responsible work'}
                        </button>
                    </fieldset>
                </form>
            </section>
            {request.canReview ? (
                <>
                    <section aria-labelledby="review-heading">
                        <h2 id="review-heading">Manual review</h2>
                        <p>
                            Examine the exact specification and file versions.
                            Upload validation is not a printability guarantee.
                            Unchecked findings are not established; a submitted
                            form alone cannot make review sufficient.
                        </p>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                beginChange();
                                review.transform((data) => ({
                                    ...data,
                                    revision: request.revision,
                                    basis: request.basis,
                                    responsible_user_id:
                                        data.responsible_user_id
                                            ? Number(data.responsible_user_id)
                                            : null,
                                }));
                                review.post(request.reviewUrl, options);
                            }}
                            aria-busy={review.processing}
                        >
                            <fieldset disabled={locked}>
                                <legend>Record manual findings</legend>
                                {(
                                    [
                                        [
                                            'complete',
                                            'I found the specification complete',
                                        ],
                                        [
                                            'material_available',
                                            'I confirmed material / color availability manually',
                                        ],
                                        [
                                            'feasible',
                                            'I found the requested work feasible / suitable',
                                        ],
                                        [
                                            'sufficient',
                                            'I consider this review sufficient on this exact basis',
                                        ],
                                    ] as const
                                ).map(([name, label]) => (
                                    <label className="review-check" key={name}>
                                        <input
                                            type="checkbox"
                                            checked={review.data[name]}
                                            onChange={(event) =>
                                                review.setData(
                                                    name,
                                                    event.target.checked,
                                                )
                                            }
                                            aria-invalid={
                                                review.errors[name]
                                                    ? true
                                                    : undefined
                                            }
                                        />
                                        {label}
                                    </label>
                                ))}
                                <div className="access-field">
                                    <label htmlFor="review-findings">
                                        Internal review findings
                                    </label>
                                    <textarea
                                        id="review-findings"
                                        value={review.data.findings}
                                        onChange={(event) =>
                                            review.setData(
                                                'findings',
                                                event.target.value,
                                            )
                                        }
                                        rows={4}
                                        maxLength={4000}
                                        required
                                        aria-invalid={
                                            review.errors.findings
                                                ? true
                                                : undefined
                                        }
                                    />
                                </div>
                                <WorkFields
                                    prefix="review"
                                    data={review.data}
                                    errors={review.errors}
                                    responsibleOptions={responsibleOptions}
                                    onChange={(key, value) =>
                                        review.setData(
                                            key as keyof typeof workDefaults,
                                            value,
                                        )
                                    }
                                />
                                <button type="submit">
                                    {review.processing
                                        ? 'Recording review…'
                                        : 'Record manual review'}
                                </button>
                            </fieldset>
                        </form>
                    </section>
                    <section aria-labelledby="decline-heading">
                        <h2 id="decline-heading">Decline unsuitable work</h2>
                        <p>
                            Decline is terminal for this intake request. Exact
                            submitted files and history remain; this is not an
                            order cancellation.
                        </p>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                beginChange();
                                decline.transform((data) => ({
                                    ...data,
                                    revision: request.revision,
                                }));
                                decline.post(request.declineUrl, options);
                            }}
                            aria-busy={decline.processing}
                        >
                            <fieldset disabled={locked}>
                                <legend>Record terminal decline</legend>
                                <div className="access-field">
                                    <label htmlFor="decline-reason">
                                        Internal decline reason
                                    </label>
                                    <textarea
                                        id="decline-reason"
                                        value={decline.data.reason}
                                        onChange={(event) =>
                                            decline.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                        rows={3}
                                        maxLength={1000}
                                        required
                                        aria-invalid={
                                            decline.errors.reason
                                                ? true
                                                : undefined
                                        }
                                    />
                                </div>
                                <div className="access-field">
                                    <label htmlFor="customer-decline-reason">
                                        Customer-safe decline explanation
                                    </label>
                                    <textarea
                                        id="customer-decline-reason"
                                        value={decline.data.customer_reason}
                                        onChange={(event) =>
                                            decline.setData(
                                                'customer_reason',
                                                event.target.value,
                                            )
                                        }
                                        rows={3}
                                        maxLength={1000}
                                        required
                                        aria-invalid={
                                            decline.errors.customer_reason
                                                ? true
                                                : undefined
                                        }
                                    />
                                </div>
                                <button type="submit">
                                    {decline.processing
                                        ? 'Recording decline…'
                                        : 'Decline request'}
                                </button>
                            </fieldset>
                        </form>
                    </section>
                </>
            ) : (
                <p role="status">
                    Assign this review to yourself before accessing design files
                    or recording review decisions. Current Owner responsibility
                    and the exact active assignment are required on every
                    action.
                </p>
            )}
            {failure && (
                <p>
                    <a href={request.detailUrl}>
                        Reload current request and history
                    </a>
                </p>
            )}
        </>
    );
}

export default function Show({
    actor,
    request,
    responsibleOptions,
    status,
}: {
    actor: Actor;
    request: ReviewDetail;
    responsibleOptions: ResponsibleOption[];
    status?: string | null;
}) {
    return (
        <>
            <Head title="Review print request — Cetakin Cloud" />
            <main className="access-page review-page">
                <h1>Review print request</h1>
                <p>Acting as {actor.email}.</p>
                <IntakeNotice />
                {status && <p role="status">{status}</p>}
                <h2>Request {request.id}</h2>
                <dl>
                    <dt>Customer for this request</dt>
                    <dd>
                        {request.customer.name} (reference {request.customer.id}
                        ); {request.customer.email}; {request.customer.phone}
                    </dd>
                    <dt>Submitted at</dt>
                    <dd>
                        <time dateTime={request.submittedAt}>
                            {request.submittedAt}
                        </time>
                    </dd>
                    <dt>Current intake status</dt>
                    <dd>{request.status}</dd>
                    <dt>Submitted specification</dt>
                    <dd>
                        {request.quantity} complete product(s)/set(s),{' '}
                        {request.material}, {request.color}
                    </dd>
                    <dt>Customer notes / printing preferences</dt>
                    <dd>
                        {request.canReview
                            ? request.notes || 'None provided.'
                            : 'Not included in this scoped view.'}
                    </dd>
                    <dt>Current review disposition</dt>
                    <dd>{reviewLabels[request.reviewDisposition]}</dd>
                    <dt>Work disposition</dt>
                    <dd>{workLabels[request.workDisposition]}</dd>
                    <dt>Responsible staff</dt>
                    <dd>
                        {request.responsible
                            ? `${request.responsible.name}${request.responsible.current ? '' : ' — current Owner responsibility missing; coverage needed'}`
                            : 'Unassigned — responsible coverage missing'}
                    </dd>
                    <dt>Next action</dt>
                    <dd>
                        {request.nextAction ||
                            (request.status === 'DECLINED'
                                ? 'No open review work — declined.'
                                : 'Not recorded — action needed.')}
                    </dd>
                </dl>
                {request.canReview && (
                    <section aria-labelledby="review-files-heading">
                        <h2 id="review-files-heading">
                            Exact files for this assigned review
                        </h2>
                        <p>
                            Review basis reference: <code>{request.basis}</code>
                            . Downloads recheck current authority; this page
                            grants no continuing file access.
                        </p>
                        <ul>
                            {request.files.map((file) => (
                                <li key={file.id}>
                                    {file.available && file.downloadUrl ? (
                                        <a href={file.downloadUrl}>
                                            Download {file.name} for review
                                        </a>
                                    ) : (
                                        <span>
                                            {file.name} — exact file currently
                                            unavailable.
                                        </span>
                                    )}
                                    <p>
                                        Version {file.id}; {file.format};{' '}
                                        {fileSize(file.sizeBytes)}.
                                    </p>
                                </li>
                            ))}
                        </ul>
                        {request.files.some((file) => !file.available) && (
                            <p role="alert">
                                An exact file is unavailable. Review cannot be
                                sufficient on unavailable content; prior review
                                evidence remains historical.
                            </p>
                        )}
                    </section>
                )}
                {request.latestReview && (
                    <section aria-labelledby="retained-review-heading">
                        <h2 id="retained-review-heading">
                            Latest retained manual review
                        </h2>
                        <p>
                            Recorded{' '}
                            <time dateTime={request.latestReview.reviewedAt}>
                                {request.latestReview.reviewedAt}
                            </time>
                            ; completeness:{' '}
                            {request.latestReview.complete
                                ? 'established'
                                : 'not established'}
                            ; material availability:{' '}
                            {request.latestReview.materialAvailable
                                ? 'established'
                                : 'not established'}
                            ; feasibility:{' '}
                            {request.latestReview.feasible
                                ? 'established'
                                : 'not established'}
                            ; recorded sufficiency:{' '}
                            {request.latestReview.sufficient
                                ? 'sufficient on its recorded basis'
                                : 'insufficient'}
                            .
                        </p>
                        <p>{request.latestReview.findings}</p>
                        <p>
                            Current effective review is shown above. Recorded
                            findings do not authorize quotation or production.
                        </p>
                    </section>
                )}
                {request.status === 'DECLINED' ? (
                    <p role="status">
                        This intake request is declined and terminal. Review,
                        reassignment and decline actions are unavailable;
                        retained evidence is unchanged.
                    </p>
                ) : (
                    <DecisionForms
                        key={request.revision}
                        request={request}
                        responsibleOptions={responsibleOptions}
                    />
                )}
                <section aria-labelledby="review-history-heading">
                    <h2 id="review-history-heading">
                        Retained review / responsibility history
                    </h2>
                    {!request.canReview && request.history.length === 0 ? (
                        <p>
                            Internal history is not included in this scoped
                            view. An empty projection does not mean earlier
                            decisions were erased.
                        </p>
                    ) : request.history.length === 0 ? (
                        <p>
                            No review or responsibility decision recorded yet.
                        </p>
                    ) : (
                        <ol>
                            {request.history.map((fact, index) => (
                                <li key={`${fact.occurredAt}-${index}`}>
                                    <strong>{fact.kind}</strong> —{' '}
                                    {fact.actorName},{' '}
                                    <time dateTime={fact.occurredAt}>
                                        {fact.occurredAt}
                                    </time>
                                    : {fact.summary}
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
                <p>
                    <a href="/staff/requests">Request review work list</a>
                    {' · '}
                    <a href="/account">Your account</a>
                </p>
                <Logout />
            </main>
        </>
    );
}
