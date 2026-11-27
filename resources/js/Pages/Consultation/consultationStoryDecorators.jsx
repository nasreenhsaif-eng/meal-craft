import { MobileStoryViewport } from '../../storybook/MobileStoryViewport.jsx';

/** Storybook decorator — full-bleed consultation canvas for mobile + desktop previews. */
export const withConsultationMobileFrame = [
    (Story) => (
        <MobileStoryViewport className="bg-surface-soft">
            <Story />
        </MobileStoryViewport>
    ),
];
