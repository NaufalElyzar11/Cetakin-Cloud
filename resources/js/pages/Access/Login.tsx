import { Head, useForm } from '@inertiajs/react';
import Field from '../../components/Access/Field';
import FormFeedback, {
    useAccessFeedback,
} from '../../components/Access/FormFeedback';
import '../../../css/access.css';

export default function Login() {
    const form = useForm({ email: '', password: '' });
    const feedback = useAccessFeedback();

    return (
        <>
            <Head title="Sign in — Cetakin Cloud" />
            <main className="access-page">
                <h1>Sign in</h1>
                <FormFeedback errors={form.errors} failure={feedback.failure} />
                <form
                    aria-busy={form.processing}
                    onSubmit={(event) => {
                        event.preventDefault();
                        feedback.clearFailure();
                        form.post('/login', {
                            onFinish: () => form.reset('password'),
                            onHttpException: feedback.onHttpException,
                            onNetworkError: feedback.onNetworkError,
                        });
                    }}
                >
                    <fieldset disabled={form.processing}>
                        <legend className="access-legend">
                            Sign-in details
                        </legend>
                        <Field
                            name="email"
                            label="Email"
                            type="email"
                            value={form.data.email}
                            onChange={(value) => form.setData('email', value)}
                            error={form.errors.email}
                            autoComplete="username"
                        />
                        <Field
                            name="password"
                            label="Password"
                            type="password"
                            value={form.data.password}
                            onChange={(value) =>
                                form.setData('password', value)
                            }
                            error={form.errors.password}
                            autoComplete="current-password"
                        />
                        <button type="submit">
                            {form.processing ? 'Signing in…' : 'Sign in'}
                        </button>
                    </fieldset>
                </form>
                <p>
                    New customer? <a href="/register">Create an account</a>
                </p>
            </main>
        </>
    );
}
