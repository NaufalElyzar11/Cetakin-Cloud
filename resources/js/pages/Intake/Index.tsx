import { Head } from '@inertiajs/react';
import Logout from '../../components/Access/Logout';
import IntakeNotice from './IntakeNotice';
import type { Actor, CustomerChoice, RequestSummary } from './types';
import '../../../css/access.css';

export default function Index({
    actor,
    customers,
    requests,
    createUrl,
    pagination,
}: {
    actor: Actor;
    customers: CustomerChoice[];
    requests: RequestSummary[];
    createUrl: string;
    pagination?: { previousUrl: string | null; nextUrl: string | null };
}) {
    return (
        <>
            <Head title="Your print requests — Cetakin Cloud" />
            <main className="access-page">
                <h1>Your print requests</h1>
                <p>Signed in as {actor.email}.</p>
                <IntakeNotice />
                {customers.length > 0 ? (
                    <p>
                        <a href={createUrl}>Submit a new print request</a>
                    </p>
                ) : (
                    <p>
                        No customer relationship currently permits a submission.
                    </p>
                )}
                {requests.length === 0 ? (
                    <p>No permitted print requests are currently available.</p>
                ) : (
                    <ul>
                        {requests.map((request) => (
                            <li key={request.id}>
                                <a href={request.detailUrl}>
                                    Request {request.id}
                                </a>
                                <p>
                                    {request.customerName}: {request.quantity}{' '}
                                    complete product(s)/set(s),{' '}
                                    {request.material}, {request.color}.
                                    Submitted — awaiting manual review.
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
                {pagination &&
                    (pagination.previousUrl || pagination.nextUrl) && (
                        <nav aria-label="Print request pages">
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
                    <a href="/account">Your account</a>
                </p>
                <Logout />
            </main>
        </>
    );
}
