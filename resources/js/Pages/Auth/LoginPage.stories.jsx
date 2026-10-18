import LoginPage from './LoginPage';

export default {
    title: 'Design System/05. Templates & Pages/Auth/LoginPage',
    component: LoginPage,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Sign-in form with static `seal-md` mark. After a successful login, the `marketing-animated` seal plays on the page, then the browser navigates to the server redirect (portal choice, onboarding, or Welcome Back).\n\n**Smart login redirect (server):** after submit, admins/staff → [`PortalChoicePage`](./PortalChoicePage.stories.jsx); customers with incomplete onboarding → `/onboarding/gender`; customers who already finished onboarding → Welcome Back (`/app`). Guests start at [`WelcomePage`](./WelcomePage.stories.jsx).',
            },
        },
    },
};

export const Default = {
    name: 'Default',
    render: () => <LoginPage />,
};
