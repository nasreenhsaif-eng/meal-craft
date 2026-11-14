import LoginPage from './LoginPage';

export default {
    title: 'Design System/05. Templates & Pages/Auth/LoginPage',
    component: LoginPage,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Sign-in form in the same phone frame as landing and onboarding. Static `seal-md` mark; after success, `marketing-animated` seal plays then redirects.\n\n**Smart login redirect (server):** admins/staff → portal choice; incomplete onboarding → `/onboarding/gender`; finished onboarding → Welcome Back (`/app`).',
            },
        },
    },
};

export const Default = {
    name: 'Default',
    render: () => <LoginPage />,
};
