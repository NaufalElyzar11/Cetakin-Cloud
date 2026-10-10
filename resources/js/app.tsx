import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import Bootstrap from './pages/Bootstrap';
import Account from './pages/Access/Account';
import Customer from './pages/Access/Customer';
import Login from './pages/Access/Login';
import Register from './pages/Access/Register';

void createInertiaApp({
    resolve: (name) => {
        switch (name) {
            case 'Bootstrap':
                return Bootstrap;
            case 'Access/Register':
                return Register;
            case 'Access/Login':
                return Login;
            case 'Access/Account':
                return Account;
            case 'Access/Customer':
                return Customer;
            default:
                throw new Error('Unknown application page.');
        }
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
