import LandingPage from './LandingPage';

export default {
    title: 'Design System/06. Pages/Landing/LandingPage',
    component: LandingPage,
    parameters: {
        layout: 'fullscreen',
        canvasBackground: 'white',
        docs: {
            description: {
                component:
                    'Public marketing landing: vertical-marketing logo hero, four meal plans (diet-protocol icons — Balanced, Ketobiotic, Cycle Sync, Sickle Cell Warrior), journey, heritage foods, zero-tolerance standard, and footer. Hero Get Started uses the design-system Button atom (primary) and opens join. The person icon at the top right opens login.',
            },
        },
    },
};

/** Full landing page. Meal plan cards use DietProtocolIcons. Get Started opens join. Person icon opens login. */
export const Default = {
    name: 'Default',
    render: () => {
        const useHerd =
            window.location.hostname === '127.0.0.1' || window.location.hostname === 'localhost';

        return (
            <LandingPage
                loginHref={useHerd ? 'https://meal-craft.test/login' : '/login'}
                joinHref={useHerd ? 'https://meal-craft.test/join' : '/join'}
            />
        );
    },
};
