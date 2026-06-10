import { MobileStoryViewport } from '../../storybook/MobileStoryViewport.jsx';

/** Storybook decorator — responsive web canvas matching production onboarding layout. */
export const withOnboardingMobileFrame = [
    (Story) => (
        <MobileStoryViewport>
            <Story />
        </MobileStoryViewport>
    ),
];
