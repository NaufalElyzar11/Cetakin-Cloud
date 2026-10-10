import { Head } from '@inertiajs/react';
import Logout from '../../components/Access/Logout';
import type { Actor, CustomerContact } from './Account';
import '../../../css/access.css';

export default function Customer({
    customer,
}: {
    actor: Actor;
    customer: CustomerContact;
}) {
    return (
        <>
            <Head title="Customer information — Cetakin Cloud" />
            <main className="access-page">
                <h1>Your customer information</h1>
                <dl>
                    <dt>Name</dt>
                    <dd>{customer.name}</dd>
                    <dt>Email</dt>
                    <dd>{customer.email}</dd>
                    <dt>WhatsApp / phone number</dt>
                    <dd>{customer.phone}</dd>
                </dl>
                <p>
                    <a href="/account">Back to your account</a>
                </p>
                <Logout />
            </main>
        </>
    );
}
