import { createInertiaApp } from '@inertiajs/react';
import { expect, test, vi } from 'vitest';
import Staff from './pages/Access/Staff';
import Create from './pages/Intake/Create';
import Index from './pages/Intake/Index';
import Show from './pages/Intake/Show';
import ReviewIndex from './pages/Review/Index';
import ReviewShow from './pages/Review/Show';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    createInertiaApp: vi.fn(() => Promise.resolve()),
}));

test('the actual browser entry resolves staff and implemented Intake pages and rejects unknown pages', async () => {
    await import('./app');
    const options = vi.mocked(createInertiaApp).mock.calls[0][0];
    const resolve = options?.resolve;
    if (!resolve)
        throw new Error('Application page resolver was not registered.');
    expect(resolve('Access/Staff')).toBe(Staff);
    expect(resolve('Intake/Index')).toBe(Index);
    expect(resolve('Intake/Create')).toBe(Create);
    expect(resolve('Intake/Show')).toBe(Show);
    expect(resolve('Review/Index')).toBe(ReviewIndex);
    expect(resolve('Review/Show')).toBe(ReviewShow);
    expect(() => resolve('Future/Order')).toThrow('Unknown application page.');
});
