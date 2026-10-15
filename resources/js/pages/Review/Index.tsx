import { Head } from '@inertiajs/react';
import Logout from '../../components/Access/Logout';
import IntakeNotice from '../Intake/IntakeNotice';
import {
    reviewLabels,
    workLabels,
    type Actor,
    type ReviewSummary,
} from './types';
import '../../../css/access.css';
import './review.css';

export default function Index({
    actor,
    requests,
    pagination,
}: {
    actor: Actor;
    requests: ReviewSummary[];
    pagination: { previousUrl: string | null; nextUrl: string | null };
}) {
    return (
        <>
            <Head title="Request review work list — Cetakin Cloud" />
            <main className="access-page review-page">
                <h1>Request review work list</h1>
                <p>Acting as {actor.email}.</p>
                <IntakeNotice />
                <p>
                    This list concerns submitted requests. Assignment records
                    responsibility for the next action. Assignment alone does
                    not grant staff privileges or unrestricted design access, or
                    establish printability, price or production approval.
                </p>
                {requests.length === 0 ? (
                    <p>No submitted request work is currently available.</p>
                ) : (
                    <ul className="review-work-list">
                        {requests.map((request) => (
                            <li key={request.id}>
                                <h2>
                                    <a href={request.detailUrl}>
                                        Request {request.id}
                                    </a>
                                </h2>
                                <p>
                                    Customer {request.customer.id}:{' '}
                                    {request.customer.name};{' '}
                                    {request.customer.email};{' '}
                                    {request.customer.phone}.
                                </p>
                                <p>
                                    Submitted{' '}
                                    <time dateTime={request.submittedAt}>
                                        {request.submittedAt}
                                    </time>
                                    . Status: {request.status}.{' '}
                                    {request.quantity} complete
                                    product(s)/set(s), {request.material},{' '}
                                    {request.color}.
                                </p>
                                <p>
                                    {request.availableFileCount === null
                                        ? `${request.fileCount} exact file version(s); current availability not checked in this work list`
                                        : `Files currently available: ${request.availableFileCount} of ${request.fileCount}`}
                                    . {reviewLabels[request.reviewDisposition]}.
                                </p>
                                <p>
                                    Work: {workLabels[request.workDisposition]}.
                                    Responsible:{' '}
                                    {request.responsible
                                        ? `${request.responsible.name}${request.responsible.current ? '' : ' — current Owner responsibility missing; coverage needed'}`
                                        : 'Unassigned — responsible coverage missing'}
                                    .
                                </p>
                                <p>
                                    Next action:{' '}
                                    {request.nextAction ||
                                        (request.status === 'DECLINED'
                                            ? 'No open review work — declined.'
                                            : 'Not recorded — action needed.')}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
                {(pagination.previousUrl || pagination.nextUrl) && (
                    <nav aria-label="Review work list pages">
                        {pagination.previousUrl && (
                            <a href={pagination.previousUrl}>
                                Previous requests
                            </a>
                        )}{' '}
                        {pagination.nextUrl && (
                            <a href={pagination.nextUrl}>Next requests</a>
                        )}
                    </nav>
                )}
                <p>
                    <a href="/staff">Staff responsibilities</a>
                    {' · '}
                    <a href="/account">Your account</a>
                </p>
                <Logout />
            </main>
        </>
    );
}
