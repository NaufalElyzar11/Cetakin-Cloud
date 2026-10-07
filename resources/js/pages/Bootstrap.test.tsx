import { render, screen, within } from '@testing-library/react';
import { expect, test, vi } from 'vitest';
import Bootstrap from './Bootstrap';

// Head needs Inertia's application context; transport is covered by PHP tests.
vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
}));

test('shows the application identity and truthful bootstrap confirmation', () => {
    render(<Bootstrap />);

    const main = within(screen.getByRole('main'));

    expect(
        main.getByRole('heading', { name: 'Cetakin Cloud', level: 1 }),
    ).toBeVisible();
    expect(
        main.getByText('The application bootstrap is running.'),
    ).toBeVisible();
});
