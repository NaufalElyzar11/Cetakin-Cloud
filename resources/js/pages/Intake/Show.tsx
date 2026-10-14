import { Head } from '@inertiajs/react';
import Logout from '../../components/Access/Logout';
import IntakeNotice from './IntakeNotice';
import { fileSize, type Actor, type SubmittedFile } from './types';
import '../../../css/access.css';

export default function Show({
    actor,
    request,
}: {
    actor: Actor;
    request: {
        id: string;
        customerId: number;
        customerName: string;
        material: string;
        color: string;
        quantity: string;
        notes: string | null;
        status: 'SUBMITTED';
        submittedAt: string;
        files: SubmittedFile[];
    };
}) {
    return (
        <>
            <Head title="Submitted print request — Cetakin Cloud" />
            <main className="access-page">
                <h1>Submitted print request</h1>
                <p>Signed in as {actor.email}.</p>
                <IntakeNotice />
                <p role="status">
                    Request {request.id} was submitted. Status: Submitted —
                    awaiting manual review.
                </p>
                <p>
                    This confirms the recorded specification and exact file
                    versions. It does not confirm printability, material
                    availability, price, quotation or approval for production.
                </p>
                <dl>
                    <dt>Customer</dt>
                    <dd>{request.customerName}</dd>
                    <dt>Service</dt>
                    <dd>Custom FDM 3D printing</dd>
                    <dt>Material</dt>
                    <dd>{request.material}</dd>
                    <dt>Color</dt>
                    <dd>{request.color}</dd>
                    <dt>Quantity of complete products/sets</dt>
                    <dd>{request.quantity}</dd>
                    <dt>Submitted at</dt>
                    <dd>
                        <time dateTime={request.submittedAt}>
                            {request.submittedAt}
                        </time>
                    </dd>
                    <dt>Optional notes / printing preferences</dt>
                    <dd>{request.notes || 'None provided.'}</dd>
                </dl>
                <h2>Exact submitted file versions</h2>
                <p>
                    Multiple files describe components of this same product or
                    set. Quantity counts complete products/sets, not files.
                </p>
                <ul>
                    {request.files.map((file) => (
                        <li key={file.id}>
                            {file.available ? (
                                <a href={file.downloadUrl}>
                                    Download {file.name}
                                </a>
                            ) : (
                                <span>
                                    {file.name} — file currently unavailable.
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
                        A recorded file is unavailable. Its exact reference is
                        retained; it has not been replaced with another design.
                    </p>
                )}
                <p>
                    <a href="/requests">Your print requests</a>
                </p>
                <Logout />
            </main>
        </>
    );
}
