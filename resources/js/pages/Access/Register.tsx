import { Head, useForm } from '@inertiajs/react';
import Field from '../../components/Access/Field';
import FormFeedback, {
    useAccessFeedback,
} from '../../components/Access/FormFeedback';
import '../../../css/access.css';

export default function Register() {
    const form = useForm({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
    });
    const feedback = useAccessFeedback();

    return (
        <>
            <Head title="Create account — Cetakin Cloud" />
            <main className="access-page">
                <h1>Create your customer account</h1>
                <FormFeedback errors={form.errors} failure={feedback.failure} />
                <form
                    aria-busy={form.processing}
                    onSubmit={(event) => {
                        event.preventDefault();
                        feedback.clearFailure();
                        form.post('/register', {
                            onFinish: () =>
                                form.reset('password', 'password_confirmation'),
                            onHttpException: feedback.onHttpException,
                            onNetworkError: feedback.onNetworkError,
                        });
                    }}
                >
                    <fieldset disabled={form.processing}>
                        <legend className="access-legend">
                            Account details
                        </legend>
                        <Field
                            name="name"
                            label="Name"
                            value={form.data.name}
                            onChange={(value) => form.setData('name', value)}
                            error={form.errors.name}
                            autoComplete="name"
                        />
                        <Field
                            name="email"
                            label="Email"
                            type="email"
                            value={form.data.email}
                            onChange={(value) => form.setData('email', value)}
                            error={form.errors.email}
                            autoComplete="email"
                        />
                        <Field
                            name="phone"
                            label="WhatsApp / phone number"
                            type="tel"
                            value={form.data.phone}
                            onChange={(value) => form.setData('phone', value)}
                            error={form.errors.phone}
                            autoComplete="tel"
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
                            autoComplete="new-password"
                            minLength={8}
                            hint="Use at least 8 characters."
                        />
                        <Field
                            name="password_confirmation"
                            label="Confirm password"
                            type="password"
                            value={form.data.password_confirmation}
                            onChange={(value) =>
                                form.setData('password_confirmation', value)
                            }
                            error={form.errors.password_confirmation}
                            autoComplete="new-password"
                        />
                        <button type="submit">
                            {form.processing
                                ? 'Creating account…'
                                : 'Create account'}
                        </button>
                    </fieldset>
                </form>
                <p>
                    Already have an account? <a href="/login">Sign in</a>
                </p>
            </main>
        </>
    );
}
