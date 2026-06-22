import LoginPage from './LoginPage';

export default {
    title: 'MealCraft/Pages/Auth/LoginPage',
    component: LoginPage,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Sign-in form with static `seal-md` mark. After a successful login, the `marketing-animated` seal plays on the page, then the browser navigates to the server redirect (portal choice, onboarding, or `/app`).\n\n**Smart login redirect (server):** after submit, admins/staff → [`PortalChoicePage`](./PortalChoicePage.stories.jsx); customers with incomplete onboarding → `/onboarding/welcome`; customers with completed onboarding → `/app`. Guests start at [`WelcomePage`](./WelcomePage.stories.jsx).',
            },
        },
    },
};

export const Default = {
    name: 'Default',
    render: () => <LoginPage />,
};
