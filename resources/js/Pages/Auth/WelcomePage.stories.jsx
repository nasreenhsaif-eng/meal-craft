import WelcomePage from './WelcomePage';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

export default {
    title: 'Design System/05. Templates & Pages/Auth/WelcomePage',
    component: WelcomePage,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Guest welcome screen in the same phone frame as onboarding. **Get Started** routes to `/login`. The live marketing front door is `Landing/LandingPage` at `/`.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

/** Default guest entry — matches onboarding frame size. */
export const Default = {
    name: 'Default',
    render: () => <WelcomePage onGetStarted={() => {}} />,
};
