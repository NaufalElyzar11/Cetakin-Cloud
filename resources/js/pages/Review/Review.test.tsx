import { router } from '@inertiajs/react';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import Index from './Index';
import Show from './Show';
import type { ReviewDetail, ReviewSummary } from './types';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

afterEach(() => vi.restoreAllMocks());

const actor = { id: 1, name: 'Synthetic Owner', email: 'owner@example.test' };
const summary: ReviewSummary = {
    id: '80000000-0000-4000-8000-000000000001',
    customer: {
        id: 10,
        name: 'Synthetic customer A',
        email: 'a@example.test',
        phone: '+62 800 000',
    },
    submittedAt: '2026-10-15T12:00:00Z',
    status: 'SUBMITTED',
    material: 'PETG',
    color: 'orange',
    quantity: '2',
    fileCount: 1,
    availableFileCount: null,
    reviewDisposition: 'NEEDS_REVIEW',
    workDisposition: 'WAITING',
    responsible: null,
    nextAction: null,
    detailUrl: '/staff/requests/80000000-0000-4000-8000-000000000001',
};
const request: ReviewDetail = {
    ...summary,
    availableFileCount: 1,
    canReview: true,
    responsible: { id: 1, name: actor.name, current: true },
    nextAction: 'Examine the synthetic request.',
    notes: 'Synthetic component set.',
    files: [
        {
            id: '90000000-0000-4000-8000-000000000001',
            name: 'synthetic.stl',
            format: 'STL',
            sizeBytes: 134,
            available: true,
            downloadUrl: `${summary.detailUrl}/files/90000000-0000-4000-8000-000000000001`,
        },
    ],
    revision: 3,
    basis: 'synthetic-exact-basis',
    latestReview: null,
    history: [],
    reviewUrl: `${summary.detailUrl}/review`,
    assignmentUrl: `${summary.detailUrl}/assignment`,
    declineUrl: `${summary.detailUrl}/decline`,
};
const responsibleOptions = [
    { id: actor.id, name: actor.name },
    { id: 2, name: 'Second synthetic Owner' },
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
function detail(overrides: Partial<ReviewDetail> = {}) {
    return render(
        <Show
            actor={actor}
            request={{ ...request, ...overrides }}
            responsibleOptions={responsibleOptions}
        />,
    );
}

test('work list makes waiting, missing coverage and unchecked availability visible without a customer directory', () => {
    render(
        <Index
            actor={actor}
            requests={[summary]}
            pagination={{
                previousUrl: null,
                nextUrl: '/staff/requests?page=2',
            }}
        />,
    );
    expect(
        screen.getByRole('link', { name: `Request ${summary.id}` }),
    ).toHaveAttribute('href', summary.detailUrl);
    expect(screen.getByText(/Work: Waiting/)).toBeVisible();
    expect(
        screen.getByText(/Unassigned — responsible coverage missing/),
    ).toBeVisible();
    expect(screen.getByText(/Not recorded — action needed/)).toBeVisible();
    expect(screen.getByText(/current availability not checked/)).toBeVisible();
    expect(screen.getByRole('link', { name: 'Next requests' })).toHaveAttribute(
        'href',
        '/staff/requests?page=2',
    );
    expect(
        screen.queryByRole('link', {
            name: /customer directory|all designs|quote|production/i,
        }),
    ).not.toBeInTheDocument();
    expect(screen.getByText(/Synthetic demonstration only/)).toBeVisible();
});

test('assignment requires deliberate real Owner selection and reason without treating responsibility as authority', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    detail({ canReview: false, files: [], responsible: null });
    expect(screen.getByRole('status')).toHaveTextContent(
        'Assign this review to yourself',
    );
    expect(
        screen.queryByRole('button', { name: 'Record manual review' }),
    ).not.toBeInTheDocument();
    expect(
        screen.queryByRole('link', { name: /Download/ }),
    ).not.toBeInTheDocument();
    fill('Responsible Owner (assignment)', '1');
    fill('Next action (assignment)', 'Inspect this exact synthetic request.');
    fill('Work disposition (assignment)', 'ACTIONABLE');
    fill('Assignment / reassignment reason', 'Owner is taking this review.');
    submit('Record responsible work');
    expect(post).toHaveBeenCalledWith(
        request.assignmentUrl,
        {
            revision: 3,
            responsible_user_id: 1,
            next_action: 'Inspect this exact synthetic request.',
            work_disposition: 'ACTIONABLE',
            reason: 'Owner is taking this review.',
        },
        expect.any(Object),
    );
    expect(screen.queryByText('synthetic.stl')).not.toBeInTheDocument();
});

test('manual review submits exact revision and basis with explicit findings and awaits server confirmation', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    detail();
    for (const label of [
        'I found the specification complete',
        'I confirmed material / color availability manually',
        'I found the requested work feasible / suitable',
        'I consider this review sufficient on this exact basis',
    ])
        fireEvent.click(screen.getByLabelText(label));
    fill(
        'Internal review findings',
        'Reviewed the exact synthetic component set.',
    );
    fill('Next action (review)', 'Await the next authorized workflow.');
    submit('Record manual review');
    expect(post).toHaveBeenCalledWith(
        request.reviewUrl,
        {
            revision: 3,
            basis: request.basis,
            complete: true,
            material_available: true,
            feasible: true,
            sufficient: true,
            findings: 'Reviewed the exact synthetic component set.',
            responsible_user_id: 1,
            next_action: 'Await the next authorized workflow.',
            work_disposition: 'WAITING',
        },
        expect.any(Object),
    );
    act(() => post.mock.calls[0][2]?.onStart?.({} as never));
    expect(screen.getByRole('status')).toHaveTextContent(
        'Waiting for the server to confirm',
    );
    expect(
        screen.getByRole('button', { name: 'Recording review…' }),
    ).toBeDisabled();
    expect(
        screen.getByRole('button', { name: 'Decline request' }),
    ).toBeDisabled();
    expect(
        screen.getByRole('link', { name: 'Download synthetic.stl for review' }),
    ).toHaveAttribute('href', request.files[0].downloadUrl);
    expect(
        screen.queryByText(
            /review recorded successfully|quote ready|approved for production/i,
        ),
    ).not.toBeInTheDocument();
});

test('insufficient or stale review rejection focuses feedback and cannot become sufficient through local form submission', () => {
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
        options?.onError?.({
            review: 'The current files are unavailable. Review cannot be sufficient.',
        });
        options?.onFinish?.({} as never);
    });
    detail({ reviewDisposition: 'INSUFFICIENT', workDisposition: 'BLOCKED' });
    fill('Internal review findings', 'Content not available for examination.');
    fireEvent.click(
        screen.getByLabelText(
            'I consider this review sufficient on this exact basis',
        ),
    );
    submit('Record manual review');
    expect(screen.getByRole('alert')).toHaveFocus();
    expect(screen.getByRole('alert')).toHaveTextContent(
        'Review cannot be sufficient',
    );
    expect(screen.getByText('Review insufficient')).toBeVisible();
    expect(screen.getByText('Blocked', { selector: 'dd' })).toBeVisible();
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
});

test('decline keeps customer-safe explanation separate from internal reason and retains the current revision', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    detail();
    fill(
        'Internal decline reason',
        'Internal synthetic feasibility assessment.',
    );
    fill(
        'Customer-safe decline explanation',
        'We cannot provide this requested print.',
    );
    submit('Decline request');
    expect(post).toHaveBeenCalledWith(
        request.declineUrl,
        {
            revision: 3,
            reason: 'Internal synthetic feasibility assessment.',
            customer_reason: 'We cannot provide this requested print.',
        },
        expect.any(Object),
    );
    expect(screen.getByText(/Decline is terminal/)).toBeVisible();
    expect(
        screen.queryByText(/request was declined successfully/i),
    ).not.toBeInTheDocument();
});

test('revoked-page and stale-basis HTTP errors require a fresh record instead of claiming success', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    detail();
    submit('Record manual review');
    act(() =>
        post.mock.calls[0][2]?.onHttpException?.({ status: 403 } as never),
    );
    expect(screen.getByRole('alert')).toHaveFocus();
    expect(screen.getByRole('alert')).toHaveTextContent(
        'open page grants no continuing access',
    );
    expect(
        screen.getByRole('link', {
            name: 'Reload current request and history',
        }),
    ).toHaveAttribute('href', request.detailUrl);
    act(() =>
        post.mock.calls[0][2]?.onHttpException?.({ status: 409 } as never),
    );
    expect(screen.getByRole('alert')).toHaveTextContent('review basis changed');
    expect(
        screen.queryByText(/review recorded successfully/i),
    ).not.toBeInTheDocument();
});

test('interrupted decision remains unknown with reload/history guidance', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    detail();
    submit('Record manual review');
    act(() => {
        post.mock.calls[0][2]?.onNetworkError?.(
            new Error('synthetic interruption'),
        );
        post.mock.calls[0][2]?.onFinish?.({} as never);
    });
    expect(screen.getByRole('alert')).toHaveTextContent('result is unknown');
    expect(screen.getByRole('alert')).toHaveTextContent(
        'do not assume the decision succeeded or failed',
    );
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
    expect(
        screen.getByRole('button', { name: 'Record manual review' }),
    ).toBeDisabled();
});

test('declined request is terminal and withheld internal history is not presented as erased', () => {
    detail({
        status: 'DECLINED',
        canReview: false,
        workDisposition: 'TERMINAL',
        reviewDisposition: 'TERMINAL',
        files: [],
        nextAction: null,
        history: [],
    });
    expect(screen.getByRole('status')).toHaveTextContent(
        'declined and terminal',
    );
    expect(
        screen.queryByRole('button', {
            name: /Record manual review|Decline request|Record responsible work/,
        }),
    ).not.toBeInTheDocument();
    expect(
        screen.getByText(
            /empty projection does not mean earlier decisions were erased/,
        ),
    ).toBeVisible();
    expect(
        screen.getByText('Declined — review no longer eligible'),
    ).toBeVisible();
});

test('changed or unavailable basis remains visible rather than asserting current sufficiency', () => {
    detail({
        reviewDisposition: 'STALE',
        latestReview: {
            complete: true,
            materialAvailable: true,
            feasible: true,
            sufficient: true,
            findings: 'Previously examined synthetic basis.',
            reviewedAt: '2026-10-15T12:05:00Z',
        },
        files: [{ ...request.files[0], available: false, downloadUrl: null }],
    });
    expect(
        screen.getByText('Review basis changed — re-review required'),
    ).toBeVisible();
    expect(
        screen.getByText(
            /recorded sufficiency: sufficient on its recorded basis/,
        ),
    ).toBeVisible();
    expect(screen.getByRole('alert')).toHaveTextContent(
        'prior review evidence remains historical',
    );
    expect(
        screen.queryByRole('link', { name: /Download/ }),
    ).not.toBeInTheDocument();
});

test('revoked responsible grant leaves missing coverage visible without files or implicit restoration', () => {
    detail({
        canReview: false,
        responsible: { id: 2, name: 'Previous Owner', current: false },
        files: [],
    });
    expect(
        screen.getByText(
            /Previous Owner — current Owner responsibility missing; coverage needed/,
        ),
    ).toBeVisible();
    expect(screen.getByLabelText('Responsible Owner (assignment)')).toHaveValue(
        '',
    );
    expect(
        screen.queryByRole('link', { name: /Download/ }),
    ).not.toBeInTheDocument();
    expect(
        screen.queryByRole('button', { name: 'Record manual review' }),
    ).not.toBeInTheDocument();
});
