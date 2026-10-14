import { router } from '@inertiajs/react';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, expect, test, vi } from 'vitest';
import Create from './Create';
import Index from './Index';
import Show from './Show';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

afterEach(() => vi.restoreAllMocks());

const actor = { id: 1, name: 'Synthetic actor', email: 'actor@example.test' };
const customers = [
    { id: 10, name: 'Synthetic customer A' },
    { id: 20, name: 'Synthetic related customer' },
];
const operationId = '40000000-0000-4000-8000-000000000001';
const checkUrl = `/requests/attempts/${operationId}`;
const props = {
    actor,
    customers,
    operationId,
    storeUrl: '/requests',
    checkUrl,
    limits: {
        maxFiles: 5,
        maxFileBytes: 26_214_400,
        maxTotalBytes: 78_643_200,
    },
};

// These C-layer cases exercise browser-reported sizes. S-layer tests inspect real bytes.
function syntheticFile(name = 'component.stl', size = 84) {
    const file = new File(['synthetic component fixture'], name, {
        type: 'application/octet-stream',
    });
    Object.defineProperty(file, 'size', { value: size });
    return file;
}
function fill(label: string, value: string) {
    fireEvent.change(screen.getByLabelText(label, { exact: true }), {
        target: { value },
    });
}
function select(files = [syntheticFile()]) {
    fireEvent.change(screen.getByLabelText('Design files (STL or 3MF)'), {
        target: { files },
    });
}
function validInputs(files = [syntheticFile()]) {
    fill('Customer', '20');
    fill('Color', 'sky blue');
    fill('Quantity of complete products/sets', '3');
    select(files);
}
function submit(label = 'Submit print request') {
    fireEvent.submit(
        screen.getByRole('button', { name: label }).closest('form')!,
    );
}

test('explicit owner and exact whole-set quantity are submitted without slicer fields or file multiplication', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    render(<Create {...props} />);
    expect(screen.getByLabelText('Customer')).toHaveValue('');
    const files = [syntheticFile('left.stl'), syntheticFile('right.3mf')];
    validInputs(files);
    fill('Quantity of complete products/sets', '9223372036854775807');
    submit();
    expect(post).toHaveBeenCalledWith(
        '/requests',
        {
            operation_id: operationId,
            customer_id: '20',
            material: 'PETG',
            color: 'sky blue',
            quantity: '9223372036854775807',
            notes: '',
            files,
        },
        expect.objectContaining({ forceFormData: true }),
    );
    expect(
        within(
            screen.getByRole('list', { name: 'Selected design files' }),
        ).getAllByRole('listitem'),
    ).toHaveLength(2);
    expect(
        screen.queryByLabelText(
            /infill|layer height|temperature|support settings/i,
        ),
    ).not.toBeInTheDocument();
    expect(screen.getByText(/Synthetic demonstration only/)).toBeVisible();
});

test('no current Customer relationship exposes no submission form or arbitrary owner', () => {
    render(<Create {...props} customers={[]} />);
    expect(
        screen.queryByRole('button', { name: 'Submit print request' }),
    ).not.toBeInTheDocument();
    expect(screen.getByText(/No current customer relationship/)).toBeVisible();
});

test('safe attempt reference is available before transfer and remains usable after session-expiration feedback', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    render(<Create {...props} />);
    expect(
        screen.getByRole('link', { name: 'Bookmark or reopen this attempt' }),
    ).toHaveAttribute('href', checkUrl);
    expect(screen.getByText(operationId, { selector: 'code' })).toBeVisible();
    validInputs();
    submit();
    act(() =>
        post.mock.calls[0][2]?.onHttpException?.({ status: 419 } as never),
    );
    expect(
        screen.getByRole('link', { name: 'Bookmark or reopen this attempt' }),
    ).toHaveAttribute('href', checkUrl);
    expect(
        screen.getByText(
            /Keep this link if your connection or session expires/,
        ),
    ).toBeVisible();
});

test('server validation is focused and attributed to the field without reporting success', () => {
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
        options?.onError?.({
            quantity: 'Quantity must be a positive whole number.',
        });
        options?.onFinish?.({} as never);
    });
    render(<Create {...props} />);
    validInputs();
    fill('Quantity of complete products/sets', '0');
    submit();
    expect(screen.getByRole('alert')).toHaveFocus();
    expect(screen.getByRole('alert')).toHaveTextContent(
        'Quantity must be a positive whole number.',
    );
    expect(
        screen.getByLabelText('Quantity of complete products/sets'),
    ).toHaveAttribute('aria-invalid', 'true');
    expect(
        screen.getByLabelText('Quantity of complete products/sets'),
    ).not.toBeDisabled();
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
});

test('empty, six-file and unsupported selections are rejected locally and remain unconfirmed', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    render(<Create {...props} />);
    submit();
    expect(screen.getByRole('alert')).toHaveTextContent('Select at least one');
    select(
        Array.from({ length: 6 }, (_, index) =>
            syntheticFile(`component-${index}.stl`),
        ),
    );
    submit();
    expect(screen.getByRole('alert')).toHaveTextContent('Select at most 5');
    select([syntheticFile('component.txt')]);
    submit();
    expect(screen.getByRole('alert')).toHaveTextContent(
        'The server also checks their content',
    );
    expect(post).not.toHaveBeenCalled();
});

test('inclusive binary size boundaries pass client feedback and one byte over is rejected', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    render(<Create {...props} />);
    validInputs([
        syntheticFile('a.stl', 26_214_400),
        syntheticFile('b.stl', 26_214_400),
        syntheticFile('c.stl', 26_214_400),
    ]);
    submit();
    expect(post).toHaveBeenCalledTimes(1);
    select([syntheticFile('a.stl', 26_214_401)]);
    submit();
    expect(screen.getByRole('alert')).toHaveTextContent(
        'Each file must be at most 25 MiB',
    );
    select([
        syntheticFile('a.stl', 26_214_400),
        syntheticFile('b.stl', 26_214_400),
        syntheticFile('c.stl', 26_214_400),
        syntheticFile('d.stl', 1),
    ]);
    submit();
    expect(screen.getByRole('alert')).toHaveTextContent('at most 75 MiB');
    expect(post).toHaveBeenCalledTimes(1);
});

test('transfer pending and 100% progress distinguish server validation from submitted confirmation', () => {
    const post = vi
        .spyOn(router, 'post')
        .mockImplementation((_url, _data, options) =>
            options?.onStart?.({} as never),
        );
    render(<Create {...props} attemptStatus="NOT_RECEIVED" />);
    validInputs();
    submit();
    expect(screen.getByRole('button', { name: 'Submitting…' })).toBeDisabled();
    expect(screen.getByLabelText('Design files (STL or 3MF)')).toBeDisabled();
    act(() => post.mock.calls[0][2]?.onProgress?.({ percentage: 60 } as never));
    expect(
        screen.getByRole('progressbar', { name: 'File transfer progress' }),
    ).toHaveAttribute('value', '60');
    expect(screen.getByRole('status')).toHaveTextContent(
        'No request is confirmed yet',
    );
    act(() =>
        post.mock.calls[0][2]?.onProgress?.({ percentage: 100 } as never),
    );
    expect(screen.getByRole('status')).toHaveTextContent(
        'Waiting for server validation and committed request confirmation',
    );
    expect(
        screen.queryByRole('heading', { name: 'Submitted print request' }),
    ).not.toBeInTheDocument();
});

test('lost response preserves attempt identity and selected files for an unchanged retry', () => {
    const post = vi
        .spyOn(router, 'post')
        .mockImplementation((_url, _data, options) =>
            options?.onStart?.({} as never),
        );
    render(<Create {...props} />);
    const files = [syntheticFile('original.stl')];
    validInputs(files);
    submit();
    act(() => {
        post.mock.calls[0][2]?.onNetworkError?.(
            new Error('synthetic network interruption'),
        );
        post.mock.calls[0][2]?.onFinish?.({} as never);
    });
    expect(screen.getByRole('alert')).toHaveTextContent(
        'The result is unknown',
    );
    expect(screen.getByRole('alert')).toHaveFocus();
    expect(
        screen.getByLabelText('Quantity of complete products/sets'),
    ).toBeDisabled();
    expect(
        screen.getByRole('button', { name: 'Retry this same attempt' }),
    ).not.toBeDisabled();
    submit('Retry this same attempt');
    expect(post.mock.calls[1][1]).toEqual(post.mock.calls[0][1]);
    expect((post.mock.calls[1][1] as { files: File[] }).files[0]).toBe(
        files[0],
    );
});

test('changed-payload conflict remains attributable to original attempt with explicit new-attempt guidance', () => {
    vi.spyOn(router, 'post').mockImplementation((_url, _data, options) => {
        options?.onError?.({
            operation_id: 'This attempt is bound to a different payload.',
        });
        options?.onFinish?.({} as never);
    });
    render(<Create {...props} />);
    validInputs();
    submit();
    expect(screen.getByRole('alert')).toHaveTextContent(
        'bound to a different payload',
    );
    expect(screen.getByLabelText('Customer')).toBeDisabled();
    expect(
        screen.getByRole('link', { name: 'start a new attempt' }),
    ).toHaveAttribute('href', '/requests/create');
    expect(screen.queryByText(/was submitted/i)).not.toBeInTheDocument();
});

test('scoped result check retains open-form state while waiting for authoritative server outcome', () => {
    const get = vi.spyOn(router, 'get').mockImplementation(() => {});
    const { rerender } = render(<Create {...props} />);
    validInputs();
    fireEvent.click(
        screen.getByRole('button', { name: 'Check this attempt result' }),
    );
    expect(get).toHaveBeenCalledWith(
        checkUrl,
        {},
        expect.objectContaining({ preserveState: true }),
    );
    act(() => get.mock.calls[0][2]?.onStart?.({} as never));
    expect(screen.getByRole('status')).toHaveTextContent(
        'Checking the current attempt result',
    );
    rerender(<Create {...props} attemptStatus="UNRESOLVED" />);
    act(() => {
        get.mock.calls[0][2]?.onSuccess?.({} as never);
        get.mock.calls[0][2]?.onFinish?.({} as never);
    });
    expect(screen.getByRole('status')).toHaveTextContent('no confirmed result');
    expect(screen.getByLabelText('Customer')).toHaveValue('20');
    expect(
        screen.getByLabelText('Quantity of complete products/sets'),
    ).toHaveValue('3');
    expect(
        screen.getByRole('list', { name: 'Selected design files' }),
    ).toHaveTextContent('component.stl');
});

test('session expiry gives sign-in and original-attempt guidance without disclosing server internals', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => {});
    render(<Create {...props} />);
    validInputs();
    submit();
    act(() =>
        post.mock.calls[0][2]?.onHttpException?.({ status: 419 } as never),
    );
    expect(screen.getByRole('alert')).toHaveTextContent(
        'Sign in again, then check this attempt',
    );
    expect(screen.getByRole('link', { name: 'Sign in' })).toHaveAttribute(
        'href',
        '/login',
    );
    expect(screen.queryByText(/was submitted/i)).not.toBeInTheDocument();
});

const request = {
    id: '50000000-0000-4000-8000-000000000001',
    customerId: 10,
    customerName: 'Synthetic customer A',
    material: 'PETG',
    color: 'black',
    quantity: '3',
    status: 'SUBMITTED' as const,
    submittedAt: '2026-10-12T12:00:00Z',
    detailUrl: '/requests/50000000-0000-4000-8000-000000000001',
};
const submittedFile = {
    id: '60000000-0000-4000-8000-000000000001',
    name: 'synthetic.stl',
    format: 'STL' as const,
    sizeBytes: 84,
    available: true,
    downloadUrl:
        '/requests/50000000-0000-4000-8000-000000000001/files/60000000-0000-4000-8000-000000000001',
};

test('request list presents only supplied owned requests and truthful submitted status', () => {
    render(
        <Index
            actor={actor}
            customers={customers}
            requests={[request]}
            createUrl="/requests/create"
            pagination={{ previousUrl: null, nextUrl: '/requests?page=2' }}
        />,
    );
    expect(
        screen.getByRole('link', { name: `Request ${request.id}` }),
    ).toHaveAttribute('href', request.detailUrl);
    expect(screen.getByText(/3 complete product\(s\)\/set\(s\)/)).toBeVisible();
    expect(screen.getByText(/awaiting manual review/)).toBeVisible();
    expect(screen.getByRole('link', { name: 'Next requests' })).toHaveAttribute(
        'href',
        '/requests?page=2',
    );
    expect(
        screen.queryByText(/Customer B|price|approved for production/i),
    ).not.toBeInTheDocument();
});

test('confirmed detail retains complete-set quantity and exact version link without implying printability', () => {
    render(
        <Show
            actor={actor}
            request={{
                ...request,
                notes: null,
                files: [
                    submittedFile,
                    {
                        ...submittedFile,
                        id: '60000000-0000-4000-8000-000000000002',
                        name: 'second.stl',
                    },
                ],
            }}
        />,
    );
    expect(screen.getByRole('status')).toHaveTextContent('was submitted');
    expect(screen.getByText('3', { selector: 'dd' })).toBeVisible();
    expect(
        screen.getByRole('link', { name: 'Download synthetic.stl' }),
    ).toHaveAttribute('href', submittedFile.downloadUrl);
    expect(
        screen.getByText(/Version 60000000-0000-4000-8000-000000000001/),
    ).toBeVisible();
    expect(screen.getByText(/does not confirm printability/)).toBeVisible();
    expect(
        screen.queryByRole('button', { name: /review|quote|produce|pay/i }),
    ).not.toBeInTheDocument();
});

test('unavailable exact version stays visible without download or substituted-file promise', () => {
    render(
        <Show
            actor={actor}
            request={{
                ...request,
                notes: 'Synthetic notes',
                files: [{ ...submittedFile, available: false }],
            }}
        />,
    );
    expect(
        screen.queryByRole('link', { name: 'Download synthetic.stl' }),
    ).not.toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent(
        'exact reference is retained',
    );
    expect(screen.getByText(/file currently unavailable/)).toBeVisible();
});
