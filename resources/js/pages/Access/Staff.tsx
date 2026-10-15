import { Head, useForm } from '@inertiajs/react';
import Field from '../../components/Access/Field';
import FormFeedback, {
    useAccessFeedback,
} from '../../components/Access/FormFeedback';
import Logout from '../../components/Access/Logout';
import type { Actor } from './Account';
import '../../../css/access.css';

type Grant = {
    id: number;
    user_id: number;
    responsibility: 'owner' | 'operator';
    granted_by: number | null;
    authorization_source: string | null;
    granted_at: string;
    grant_reason: string;
    revoked_at: string | null;
    revoked_by: number | null;
    revoke_reason: string | null;
};

export default function Staff({
    actor,
    users,
    grants,
    status,
}: {
    actor: Actor;
    users: Actor[];
    grants: Grant[];
    status?: string | null;
}) {
    const form = useForm({
        user_id: '',
        responsibility: 'operator',
        reason: '',
    });
    const revoke = useForm({ grant_id: '', reason: '' });
    const feedback = useAccessFeedback();
    const pending = form.processing || revoke.processing;

    return (
        <>
            <Head title="Staff responsibilities — Cetakin Cloud" />
            <main className="access-page">
                <h1>Staff responsibilities</h1>
                <p>
                    Acting as {actor.email}. Operator responsibility alone
                    grants no production-work access.
                </p>
                <p>
                    <a href="/staff/requests">Request review work list</a>
                </p>
                {status && <p role="status">{status}</p>}
                <FormFeedback
                    errors={{ ...form.errors, ...revoke.errors }}
                    failure={feedback.failure}
                />
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        feedback.clearFailure();
                        form.post('/staff/responsibilities', {
                            onSuccess: () => form.reset('reason'),
                            onHttpException: feedback.onHttpException,
                            onNetworkError: feedback.onNetworkError,
                        });
                    }}
                    aria-busy={form.processing}
                >
                    <fieldset disabled={pending}>
                        <legend>Grant responsibility</legend>
                        <label htmlFor="staff-user">Existing user</label>
                        <select
                            id="staff-user"
                            value={form.data.user_id}
                            onChange={(event) =>
                                form.setData('user_id', event.target.value)
                            }
                            required
                        >
                            <option value="">Select a user</option>
                            {users.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name} ({user.email})
                                </option>
                            ))}
                        </select>
                        <label htmlFor="staff-responsibility">
                            Responsibility
                        </label>
                        <select
                            id="staff-responsibility"
                            value={form.data.responsibility}
                            onChange={(event) =>
                                form.setData(
                                    'responsibility',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="operator">
                                Production Operator
                            </option>
                            <option value="owner">Owner / Administrator</option>
                        </select>
                        <Field
                            name="grant-reason"
                            label="Grant reason"
                            value={form.data.reason}
                            onChange={(value) => form.setData('reason', value)}
                            error={form.errors.reason}
                            autoComplete="off"
                        />
                        <button type="submit">
                            {form.processing
                                ? 'Granting…'
                                : 'Grant responsibility'}
                        </button>
                    </fieldset>
                </form>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        feedback.clearFailure();
                        revoke.transform(({ reason }) => ({ reason }));
                        revoke.post(
                            `/staff/responsibilities/${revoke.data.grant_id}/revoke`,
                            {
                                onSuccess: () => revoke.reset(),
                                onHttpException: feedback.onHttpException,
                                onNetworkError: feedback.onNetworkError,
                            },
                        );
                    }}
                    aria-busy={revoke.processing}
                >
                    <fieldset disabled={pending}>
                        <legend>Revoke responsibility</legend>
                        <label htmlFor="staff-grant">Active grant</label>
                        <select
                            id="staff-grant"
                            value={revoke.data.grant_id}
                            onChange={(event) =>
                                revoke.setData('grant_id', event.target.value)
                            }
                            required
                        >
                            <option value="">Select a grant</option>
                            {grants
                                .filter((grant) => grant.revoked_at === null)
                                .map((grant) => (
                                    <option key={grant.id} value={grant.id}>
                                        User {grant.user_id}:{' '}
                                        {grant.responsibility} (grant {grant.id}
                                        )
                                    </option>
                                ))}
                        </select>
                        <Field
                            name="revoke-reason"
                            label="Revoke reason"
                            value={revoke.data.reason}
                            onChange={(value) =>
                                revoke.setData('reason', value)
                            }
                            error={revoke.errors.reason}
                            autoComplete="off"
                        />
                        <p>
                            Revocation changes permission immediately. It does
                            not stop printers or confirm stopped work. The last
                            Owner cannot be revoked.
                        </p>
                        <button type="submit">
                            {revoke.processing
                                ? 'Revoking…'
                                : 'Revoke responsibility'}
                        </button>
                    </fieldset>
                </form>
                <h2>Retained responsibility history</h2>
                <ul>
                    {grants.map((grant) => (
                        <li key={grant.id}>
                            Grant {grant.id}: user {grant.user_id},{' '}
                            {grant.responsibility}; granted {grant.granted_at}{' '}
                            by {grant.granted_by ?? grant.authorization_source},
                            reason: {grant.grant_reason}.
                            {grant.revoked_at
                                ? ` Revoked ${grant.revoked_at} by ${grant.revoked_by}, reason: ${grant.revoke_reason}.`
                                : ' Active.'}
                        </li>
                    ))}
                </ul>
                <p>
                    Verified credential recovery uses the trusted offline
                    procedure.
                </p>
                <a href="/account">Your customer account</a>
                <Logout />
            </main>
        </>
    );
}
