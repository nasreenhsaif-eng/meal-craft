import LoginPage from './LoginPage';

export default {
    title: 'Design System/05. Templates & Pages/Auth/LoginPage',
    component: LoginPage,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Sign-in form with static `seal-md` mark. After a successful login, the `marketing-animated` seal plays on the page, then the browser navigates to the server redirect (portal choice, onboarding, Crafted for you, or Welcome Back).\n\n**Smart login redirect (server):** after submit, admins/staff → [`PortalChoicePage`](./PortalChoicePage.stories.jsx); customers with incomplete onboarding → `/onboarding/gender`; customers with completed profile but no meal plan → Crafted for you; customers with completed profile **and** submitted meal choices → Welcome Back (`/app`). Guests start at [`WelcomePage`](./WelcomePage.stories.jsx).',
            },
        },
    },
};

export const Default = {
    name: 'Default',
    render: () => <LoginPage />,
};
