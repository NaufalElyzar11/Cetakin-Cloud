import { useForm } from '@inertiajs/react';
import FormFeedback, { useAccessFeedback } from './FormFeedback';

export default function Logout() {
    const form = useForm({});
    const feedback = useAccessFeedback();

    return (
        <form
            aria-busy={form.processing}
            onSubmit={(event) => {
                event.preventDefault();
                feedback.clearFailure();
                form.post('/logout', {
                    onHttpException: feedback.onHttpException,
                    onNetworkError: feedback.onNetworkError,
                });
            }}
        >
            <FormFeedback errors={form.errors} failure={feedback.failure} />
            <button type="submit" disabled={form.processing}>
                {form.processing ? 'Signing out…' : 'Sign out'}
            </button>
        </form>
    );
}
