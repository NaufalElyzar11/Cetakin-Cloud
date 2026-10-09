import { Head } from '@inertiajs/react';
import Logout from '../../components/Access/Logout';
import '../../../css/access.css';

export type Actor = { id: number; name: string; email: string };
export type CustomerContact = {
    id: number;
    name: string;
    email: string;
    phone: string;
};

export default function Account({
    actor,
    customers,
}: {
    actor: Actor;
    customers: (CustomerContact & { detailUrl: string })[];
}) {
    return (
        <>
            <Head title="Your account — Cetakin Cloud" />
            <main className="access-page">
                <h1>Your account</h1>
                <p>Signed in as {actor.email}.</p>
                <h2>Your customer information</h2>
                {customers.length === 0 ? (
                    <p>
                        No customer information is currently available to this
                        account.
                    </p>
                ) : (
                    <ul>
                        {customers.map((customer) => (
                            <li key={customer.id}>
                                <a href={customer.detailUrl}>{customer.name}</a>
                            </li>
                        ))}
                    </ul>
                )}
                <Logout />
            </main>
        </>
    );
}
