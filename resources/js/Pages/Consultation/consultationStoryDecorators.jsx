import { MobileStoryViewport } from '../../storybook/MobileStoryViewport.jsx';

/** Storybook decorator — full-bleed consultation canvas for mobile + desktop previews. */
export const withConsultationMobileFrame = [
    (Story) => (
        <MobileStoryViewport className="bg-[#F8F9F6]">
            <Story />
        </MobileStoryViewport>
    ),
];
