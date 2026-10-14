import JoinPage from './JoinPage';

export default {
    title: 'Design System/05. Templates & Pages/Auth/JoinPage',
    component: JoinPage,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Customer sign-up UI for `/join` — vertical lockup, name / email / mobile / password / confirm, **Create account** pill, and a **Log in** link. Live registration posts through Fortify (`register.store`).',
            },
        },
    },
};

export const Default = {
    name: 'Sign up',
    render: () => <JoinPage />,
};

export const ValidationError = {
    name: 'Validation error',
    render: () => (
        <JoinPage
            initialName="Nasreen"
            initialEmail="not-an-email"
            initialPhone="123"
            emailError="Please enter a valid email address."
            phoneError="Enter a valid mobile number, including the country code."
            passwordError="Password must be at least 8 characters."
        />
    ),
};
