import { router } from '@inertiajs/react';
import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import Staff from './Staff';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

afterEach(() => vi.restoreAllMocks());

const actor = { id: 1, name: 'Owner', email: 'owner@example.test' };
const target = { id: 2, name: 'Operator', email: 'operator@example.test' };
const grants = [
    {
        id: 5,
        user_id: 2,
        responsibility: 'operator' as const,
        granted_by: 1,
        authorization_source: null,
        granted_at: '2026-10-12T12:00:00Z',
        grant_reason: 'Confirmed operator responsibility',
        revoked_at: null,
        revoked_by: null,
        revoke_reason: null,
    },
];

function fill(label: string, value: string) {
    fireEvent.change(screen.getByLabelText(label, { exact: true }), {
        target: { value },
    });
}

function submit(label: string) {
    fireEvent.submit(
        screen.getByRole('button', { name: label }).closest('form')!,
    );
}

test('Owner grant submits a real user and fixed responsibility with reason, never acting authority', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    render(<Staff actor={actor} users={[actor, target]} grants={grants} />);
    fill('Existing user', '2');
    fill('Responsibility', 'operator');
    fill('Grant reason', 'Confirmed operational responsibility');
    submit('Grant responsibility');
    expect(post).toHaveBeenCalledWith(
        '/staff/responsibilities',
        {
            user_id: '2',
            responsibility: 'operator',
            reason: 'Confirmed operational responsibility',
        },
        expect.any(Object),
    );
    expect(
        screen.getByText(
            /Operator responsibility alone grants no production-work access/,
        ),
    ).toBeInTheDocument();
    expect(
        screen.queryByLabelText(/password|customer profile/i),
    ).not.toBeInTheDocument();
});

test('revocation sends exact grant and reason without claiming physical work stopped', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    render(<Staff actor={actor} users={[actor, target]} grants={grants} />);
    fill('Active grant', '5');
    fill('Revoke reason', 'Responsibility removed');
    submit('Revoke responsibility');
    expect(post).toHaveBeenCalledWith(
        '/staff/responsibilities/5/revoke',
        { reason: 'Responsibility removed' },
        expect.any(Object),
    );
    expect(
        screen.getByText(/does not stop printers or confirm stopped work/),
    ).toBeInTheDocument();
    expect(
        screen.getByText(/Confirmed operator responsibility/),
    ).toBeInTheDocument();
});

test('guard rejection is visible and focuses feedback without a false successful grant', () => {
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
        options?.onError?.({
            access: 'The last active Owner cannot be revoked.',
        });
        options?.onFinish?.({} as never);
    });
    render(<Staff actor={actor} users={[actor, target]} grants={grants} />);
    fill('Active grant', '5');
    fill('Revoke reason', 'Attempted removal');
    submit('Revoke responsibility');
    const feedback = screen.getByRole('alert', {
        name: 'Request not completed',
    });
    expect(feedback).toHaveTextContent(
        'The last active Owner cannot be revoked.',
    );
    expect(feedback).toHaveFocus();
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
});
