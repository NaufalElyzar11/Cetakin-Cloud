import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';
import Bootstrap from './pages/Bootstrap';
import Account from './pages/Access/Account';
import Customer from './pages/Access/Customer';
import Login from './pages/Access/Login';
import Register from './pages/Access/Register';
import Staff from './pages/Access/Staff';
import RequestIndex from './pages/Intake/Index';
import RequestCreate from './pages/Intake/Create';
import RequestShow from './pages/Intake/Show';
import ReviewIndex from './pages/Review/Index';
import ReviewShow from './pages/Review/Show';

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
            case 'Access/Staff':
                return Staff;
            case 'Intake/Index':
                return RequestIndex;
            case 'Intake/Create':
                return RequestCreate;
            case 'Intake/Show':
                return RequestShow;
            case 'Review/Index':
                return ReviewIndex;
            case 'Review/Show':
                return ReviewShow;
            default:
                throw new Error('Unknown application page.');
        }
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
