import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import FormFeedback from '../../components/Access/FormFeedback';
import Logout from '../../components/Access/Logout';
import IntakeNotice from './IntakeNotice';
import {
    fileSize,
    type Actor,
    type CustomerChoice,
    type UploadLimits,
} from './types';
import '../../../css/access.css';
import './intake.css';

type Stage =
    | 'idle'
    | 'transferring'
    | 'validating'
    | 'checking'
    | 'rejected'
    | 'unconfirmed';

function selectionError(files: File[], limits: UploadLimits): string {
    if (files.length === 0)
        return 'Select at least one STL or 3MF design file.';
    if (files.length > limits.maxFiles)
        return `Select at most ${limits.maxFiles} design files for the same product or component set.`;
    if (files.some((file) => file.size > limits.maxFileBytes))
        return 'Each file must be at most 25 MiB (26,214,400 bytes).';
    if (
        files.reduce((total, file) => total + file.size, 0) >
        limits.maxTotalBytes
    )
        return 'The selected files must total at most 75 MiB (78,643,200 bytes).';
    if (files.some((file) => !/\.(stl|3mf)$/i.test(file.name)))
        return 'Select STL or 3MF files. The server also checks their content.';
    return '';
}

export default function Create({
    actor,
    customers,
    operationId,
    storeUrl,
    checkUrl,
    limits,
    attemptStatus,
}: {
    actor: Actor;
    customers: CustomerChoice[];
    operationId: string;
    storeUrl: string;
    checkUrl: string;
    limits: UploadLimits;
    attemptStatus?: 'UNRESOLVED' | 'NOT_RECEIVED' | null;
}) {
    const form = useForm({
        operation_id: operationId,
        customer_id: '',
        material: 'PETG',
        color: '',
        quantity: '',
        notes: '',
        files: [] as File[],
    });
    const [stage, setStage] = useState<Stage>('idle');
    const [percentage, setPercentage] = useState<number | null>(null);
    const [failure, setFailure] = useState('');
    const [fileError, setFileError] = useState('');
    const pending = form.processing || stage === 'checking';
    const locked = pending || stage === 'unconfirmed';
    const errors = form.errors as Record<string, string | undefined>;
    const allErrors = { ...errors, ...(fileError ? { files: fileError } : {}) };
    const fileMessages = Object.entries(allErrors)
        .filter(([key]) => key === 'files' || key.startsWith('files.'))
        .map(([, value]) => value)
        .filter(Boolean)
        .join(' ');

    function onHttpException({ status }: { status: number }) {
        setStage('unconfirmed');
        setFailure(
            status === 401 || status === 419
                ? 'Your session is no longer valid. Sign in again, then check this attempt before creating another request.'
                : status === 403 || status === 404
                  ? 'Current access does not permit this action. No successful submission is confirmed.'
                  : status === 429
                    ? 'Too many attempts. Wait, then check or retry this same attempt.'
                    : 'The server did not confirm the result. Check this attempt or retry its unchanged files and specification.',
        );
        return false;
    }
    function onNetworkError() {
        setStage('unconfirmed');
        setFailure(
            'The result is unknown because the connection failed. Check this attempt or retry the same files and specification; do not assume submission failed or succeeded.',
        );
        return false;
    }
    function checkResult() {
        setFailure('');
        router.get(
            checkUrl,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => setStage('checking'),
                onSuccess: () => setStage('unconfirmed'),
                onHttpException,
                onNetworkError,
                onFinish: () =>
                    setStage((current) =>
                        current === 'checking' ? 'unconfirmed' : current,
                    ),
            },
        );
    }
    function fieldError(name: string) {
        return errors[name] ? (
            <p id={`${name}-error`} className="access-field-error">
                {errors[name]}
            </p>
        ) : null;
    }

    return (
        <>
            <Head title="New print request — Cetakin Cloud" />
            <main className="access-page intake-page">
                <h1>New print request</h1>
                <p>Signed in as {actor.email}.</p>
                <IntakeNotice />
                <p>
                    Attempt reference: <code>{operationId}</code>.{' '}
                    <a href={checkUrl}>Bookmark or reopen this attempt</a>{' '}
                    before submitting. Keep this link if your connection or
                    session expires; sign in again and reopen it to check the
                    recorded result.
                </p>
                <p>
                    One request describes one product or one explicitly defined
                    component set. Multiple files are components of that same
                    product/set. Staff will review feasibility and availability
                    before any manual quotation.
                </p>
                <FormFeedback errors={allErrors} failure={failure} />
                {stage === 'transferring' && (
                    <div role="status">
                        <p>
                            Transferring files
                            {percentage === null ? '…' : `: ${percentage}%.`} No
                            request is confirmed yet.
                        </p>
                        {percentage !== null && (
                            <progress
                                aria-label="File transfer progress"
                                value={percentage}
                                max={100}
                            />
                        )}
                    </div>
                )}
                {stage === 'validating' && (
                    <p role="status">
                        Transfer complete. Waiting for server validation and
                        committed request confirmation. A 100% transfer is not a
                        submitted request.
                    </p>
                )}
                {stage === 'checking' && (
                    <p role="status">Checking the current attempt result…</p>
                )}
                {(stage === 'unconfirmed' ||
                    (stage === 'idle' && attemptStatus)) && (
                    <p role="status">
                        {stage === 'idle' && attemptStatus === 'NOT_RECEIVED'
                            ? 'The last result check found no confirmed result.'
                            : 'This page has no confirmed result for this attempt.'}{' '}
                        Check again or retry with the unchanged files and
                        specification. Only the submitted request page confirms
                        success.
                    </p>
                )}
                {customers.length === 0 ? (
                    <p>
                        No current customer relationship permits submitting a
                        request. This page grants no ownership.
                    </p>
                ) : (
                    <form
                        aria-busy={pending}
                        onSubmit={(event) => {
                            event.preventDefault();
                            const problem = selectionError(
                                form.data.files,
                                limits,
                            );
                            setFileError(problem);
                            setFailure('');
                            if (problem) {
                                setStage('rejected');
                                return;
                            }
                            form.clearErrors();
                            form.post(storeUrl, {
                                forceFormData: true,
                                preserveScroll: true,
                                onStart: () => {
                                    setPercentage(null);
                                    setStage('transferring');
                                },
                                onProgress: (progress) => {
                                    const current =
                                        progress?.percentage ?? null;
                                    setPercentage(current);
                                    setStage(
                                        current !== null && current >= 100
                                            ? 'validating'
                                            : 'transferring',
                                    );
                                },
                                onError: (rejections) =>
                                    setStage(
                                        'submission' in rejections ||
                                            'operation_id' in rejections
                                            ? 'unconfirmed'
                                            : 'rejected',
                                    ),
                                onHttpException,
                                onNetworkError,
                                onFinish: () =>
                                    setStage((current) =>
                                        current === 'transferring' ||
                                        current === 'validating'
                                            ? 'unconfirmed'
                                            : current,
                                    ),
                            });
                        }}
                    >
                        <fieldset disabled={locked}>
                            <legend className="access-legend">
                                Specification and design files
                            </legend>
                            <div className="access-field">
                                <label htmlFor="request-customer">
                                    Customer
                                </label>
                                <select
                                    id="request-customer"
                                    name="customer_id"
                                    value={form.data.customer_id}
                                    required
                                    aria-invalid={
                                        errors.customer_id ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.customer_id
                                            ? 'customer_id-error'
                                            : undefined
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            'customer_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        Select the owning customer
                                    </option>
                                    {customers.map((customer) => (
                                        <option
                                            key={customer.id}
                                            value={customer.id}
                                        >
                                            {customer.name}
                                        </option>
                                    ))}
                                </select>
                                {fieldError('customer_id')}
                            </div>
                            <div className="access-field">
                                <label htmlFor="request-material">
                                    Material
                                </label>
                                <select
                                    id="request-material"
                                    name="material"
                                    value={form.data.material}
                                    required
                                    aria-invalid={
                                        errors.material ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.material
                                            ? 'material-error'
                                            : undefined
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            'material',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="PETG">PETG</option>
                                </select>
                                {fieldError('material')}
                            </div>
                            <div className="access-field">
                                <label htmlFor="request-color">Color</label>
                                <select
                                    id="request-color"
                                    name="color"
                                    value={form.data.color}
                                    required
                                    aria-invalid={
                                        errors.color ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.color ? 'color-error' : undefined
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            'color',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Select a color</option>
                                    <option value="orange">Orange</option>
                                    <option value="sky blue">Sky Blue</option>
                                    <option value="white">White</option>
                                    <option value="black">Black</option>
                                </select>
                                {fieldError('color')}
                            </div>
                            <div className="access-field">
                                <label htmlFor="request-quantity">
                                    Quantity of complete products/sets
                                </label>
                                <input
                                    id="request-quantity"
                                    name="quantity"
                                    type="text"
                                    inputMode="numeric"
                                    pattern="[1-9][0-9]*"
                                    value={form.data.quantity}
                                    required
                                    aria-invalid={
                                        errors.quantity ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.quantity
                                            ? 'quantity-hint quantity-error'
                                            : 'quantity-hint'
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            'quantity',
                                            event.target.value,
                                        )
                                    }
                                />
                                <p id="quantity-hint">
                                    Use a positive whole number. Quantity counts
                                    complete products/sets, regardless of file
                                    count.
                                </p>
                                {fieldError('quantity')}
                            </div>
                            <div className="access-field">
                                <label htmlFor="request-files">
                                    Design files (STL or 3MF)
                                </label>
                                <input
                                    id="request-files"
                                    name="files[]"
                                    type="file"
                                    accept=".stl,.3mf"
                                    multiple
                                    required
                                    aria-invalid={
                                        fileMessages ? true : undefined
                                    }
                                    aria-describedby={
                                        fileMessages
                                            ? 'files-hint files-error'
                                            : 'files-hint'
                                    }
                                    onChange={(event) => {
                                        const selected = Array.from(
                                            event.target.files ?? [],
                                        );
                                        form.setData('files', selected);
                                        setFileError(
                                            selectionError(selected, limits),
                                        );
                                    }}
                                />
                                <p id="files-hint">
                                    Select 1–5 files, at most 25 MiB (26,214,400
                                    bytes) each and 75 MiB (78,643,200 bytes)
                                    combined. The server validates content; a
                                    matching extension does not establish
                                    validity or printability.
                                </p>
                                {fileMessages && (
                                    <p
                                        id="files-error"
                                        className="access-field-error"
                                    >
                                        {fileMessages}
                                    </p>
                                )}
                                {form.data.files.length > 0 && (
                                    <ul aria-label="Selected design files">
                                        {form.data.files.map((file, index) => (
                                            <li key={index}>
                                                {file.name} —{' '}
                                                {fileSize(file.size)}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                            <div className="access-field">
                                <label htmlFor="request-notes">
                                    Optional notes / printing preferences
                                </label>
                                <textarea
                                    id="request-notes"
                                    name="notes"
                                    rows={4}
                                    value={form.data.notes}
                                    aria-invalid={
                                        errors.notes ? true : undefined
                                    }
                                    aria-describedby={
                                        errors.notes ? 'notes-error' : undefined
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                />
                                {fieldError('notes')}
                            </div>
                        </fieldset>
                        <button type="submit" disabled={pending}>
                            {form.processing
                                ? 'Submitting…'
                                : stage === 'unconfirmed'
                                  ? 'Retry this same attempt'
                                  : 'Submit print request'}
                        </button>
                    </form>
                )}
                <p>
                    <button
                        type="button"
                        disabled={pending}
                        onClick={checkResult}
                    >
                        Check this attempt result
                    </button>
                </p>
                <p>
                    To change an unresolved attempt, first check its result,
                    then deliberately{' '}
                    <a href="/requests/create">start a new attempt</a>. A new
                    attempt can create a separate request. Files are kept only
                    in this open form; reloading requires selecting them again.
                </p>
                <p>
                    <a href="/login">Sign in</a> ·{' '}
                    <a href="/requests">Your print requests</a>
                </p>
                <Logout />
            </main>
        </>
    );
}
