import { router } from '@inertiajs/react';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import Account from './Account';
import Customer from './Customer';
import Login from './Login';
import Register from './Register';

// Keep the real form helper; isolate only document Head and the HTTP boundary.
vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

afterEach(() => vi.restoreAllMocks());

function fill(label: string, value: string) {
    fireEvent.change(screen.getByLabelText(label, { exact: true }), {
        target: { value },
    });
}

function submit(label: string) {
    const button = screen.getByRole('button', { name: label });
    fireEvent.submit(button.closest('form')!);
}

const actor = { id: 1, name: 'Customer A', email: 'a@example.test' };
const customer = {
    id: 10,
    name: 'Customer A',
    email: 'contact@example.test',
    phone: '+62 800 000',
};

test('registration has approved contact fields and submits only account inputs', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    render(<Register />);
    fill('Name', 'Customer A');
    fill('Email', 'a@example.test');
    fill('WhatsApp / phone number', '+62 800 000');
    fill('Password', 'synthetic-password');
    fill('Confirm password', 'synthetic-password');
    submit('Create account');

    expect(post).toHaveBeenCalledWith(
        '/register',
        {
            name: 'Customer A',
            email: 'a@example.test',
            phone: '+62 800 000',
            password: 'synthetic-password',
            password_confirmation: 'synthetic-password',
        },
        expect.any(Object),
    );
    expect(
        screen.queryByRole('link', { name: /verify|reset|staff/i }),
    ).not.toBeInTheDocument();
});

test('registration rejection preserves contact input, clears passwords and focuses errors', () => {
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
        options?.onError?.({ email: 'This email cannot be used.' });
        options?.onFinish?.({} as never);
    });
    render(<Register />);
    fill('Name', 'Customer A');
    fill('Email', 'a@example.test');
    fill('Password', 'synthetic-password');
    fill('Confirm password', 'synthetic-password');
    submit('Create account');

    const alert = screen.getByRole('alert');
    expect(within(alert).getByText('This email cannot be used.')).toBeVisible();
    expect(alert).toHaveFocus();
    expect(screen.getByLabelText('Email', { exact: true })).toHaveValue(
        'a@example.test',
    );
    expect(screen.getByLabelText('Password', { exact: true })).toHaveValue('');
    expect(screen.getByLabelText('Confirm password')).toHaveValue('');
    expect(screen.getByLabelText('Email', { exact: true })).toHaveAttribute(
        'aria-invalid',
        'true',
    );
});

test('login pending state prevents duplicate input without claiming success', () => {
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
        options?.onStart?.({} as never);
    });
    render(<Login />);
    fill('Email', 'a@example.test');
    fill('Password', 'synthetic-password');
    submit('Sign in');
    expect(screen.getByRole('button', { name: 'Signing in…' })).toBeDisabled();
    expect(screen.getByLabelText('Email')).toBeDisabled();
    expect(screen.queryByText(/signed in as/i)).not.toBeInTheDocument();
});

test('invalid login shows generic server rejection and clears the password', () => {
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
        options?.onError?.({
            email: 'These credentials could not be accepted.',
        });
        options?.onFinish?.({} as never);
    });
    render(<Login />);
    fill('Email', 'a@example.test');
    fill('Password', 'wrong-synthetic-password');
    submit('Sign in');
    expect(
        within(screen.getByRole('alert')).getByText(
            'These credentials could not be accepted.',
        ),
    ).toBeVisible();
    expect(screen.getByLabelText('Password')).toHaveValue('');
    expect(screen.getByLabelText('Email')).toHaveValue('a@example.test');
});

test('expired-session response gives recovery guidance instead of success', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    render(<Login />);
    submit('Sign in');
    act(() =>
        post.mock.calls[0][2]?.onHttpException?.({ status: 419 } as never),
    );
    expect(screen.getByRole('alert')).toHaveTextContent(
        'Your session has expired. Reload this page before trying again.',
    );
    expect(screen.getByRole('alert')).toHaveFocus();
});

test('unconfirmed network result remains an error', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    render(<Register />);
    submit('Create account');
    act(() =>
        post.mock.calls[0][2]?.onNetworkError?.(
            new Error('synthetic connection failure'),
        ),
    );
    expect(screen.getByRole('alert')).toHaveTextContent(
        'The request could not be confirmed. Check your connection before trying again.',
    );
});

test('account displays only supplied customer links and has no management actions', () => {
    render(
        <Account
            actor={actor}
            customers={[{ ...customer, detailUrl: '/account/customers/10' }]}
        />,
    );
    expect(screen.getByText('Signed in as a@example.test.')).toBeVisible();
    expect(screen.getByRole('link', { name: 'Customer A' })).toHaveAttribute(
        'href',
        '/account/customers/10',
    );
    expect(
        screen.queryByRole('link', { name: /edit|manage|staff|customer b/i }),
    ).not.toBeInTheDocument();
});

test('account with removed relationships shows no stale customer link', () => {
    const { rerender } = render(
        <Account
            actor={actor}
            customers={[{ ...customer, detailUrl: '/account/customers/10' }]}
        />,
    );
    rerender(<Account actor={actor} customers={[]} />);
    expect(
        screen.getByText(
            'No customer information is currently available to this account.',
        ),
    ).toBeVisible();
    expect(
        screen.queryByRole('link', { name: 'Customer A' }),
    ).not.toBeInTheDocument();
});

test('customer contact is read-only and logout waits for the server', () => {
    const post = vi
        .spyOn(router, 'post')
        .mockImplementation((_url, _data, options) => {
            options?.onStart?.({} as never);
        });
    render(<Customer actor={actor} customer={customer} />);
    expect(screen.getByText('contact@example.test')).toBeVisible();
    expect(screen.getByText('+62 800 000')).toBeVisible();
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    submit('Sign out');
    expect(post).toHaveBeenCalledWith('/logout', {}, expect.any(Object));
    expect(screen.getByRole('button', { name: 'Signing out…' })).toBeDisabled();
    expect(screen.getByText('contact@example.test')).toBeVisible();
});
