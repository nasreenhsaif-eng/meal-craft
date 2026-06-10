import WelcomePage from './WelcomePage';

export default {
    title: 'MealCraft/Pages/Auth/WelcomePage',
    component: WelcomePage,
    parameters: {
        layout: 'fullscreen',
        viewport: {
            defaultViewport: 'mobile1',
        },
        docs: {
            description: {
                component:
                    'Public landing at `/` and `/welcome`. `vertical-marketing` lockup with fade-in; **Get Started** routes to `/login`. Authenticated users are redirected server-side to their home path.',
            },
        },
    },
};

/** Default guest entry — matches production welcome screen. */
export const Default = {
    name: 'Default',
    render: () => <WelcomePage onGetStarted={() => {}} />,
};
