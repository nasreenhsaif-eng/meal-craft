import LandingPage from './LandingPage';

export default {
    title: 'Design System/05. Templates & Pages/Landing/LandingPage',
    component: LandingPage,
    parameters: {
        layout: 'fullscreen',
        canvasBackground: 'white',
        docs: {
            description: {
                component:
                    'Public marketing landing at full desktop width. Start now opens the login page.',
            },
        },
    },
};

/** Full landing page. Start now opens login. Learn More stays on the canvas. */
export const Default = {
    name: 'Default',
    render: () => (
        <LandingPage
            loginHref={
                window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost'
                    ? 'https://meal-craft.test/login'
                    : '/login'
            }
            onLearnMore={() => {}}
        />
    ),
};
