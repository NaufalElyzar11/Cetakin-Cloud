import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import Bootstrap from './pages/Bootstrap';

void createInertiaApp({
    resolve: (name) => {
        if (name !== 'Bootstrap') {
            throw new Error('Unknown application page.');
        }

        return Bootstrap;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
