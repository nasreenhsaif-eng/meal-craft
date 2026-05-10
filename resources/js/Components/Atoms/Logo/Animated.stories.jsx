import MealCraftLogo from './MealCraftLogo.jsx';

/** Motion-driven identity tiers (`minimal-animated`, `smart-animated`, `marketing-animated`). */

/** @type {import('@storybook/react-vite').Meta} */
const meta = {
    title: 'MealCraft/Identity/Animated',
    component: MealCraftLogo,
    parameters: {
        controls: { disable: true },
    },
};

export default meta;

/** @type {import('@storybook/react-vite').StoryObj} */
export const MinimalAnimated = {
    render: () => (
        <div className="flex flex-col gap-4 p-6">
            <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                Standalone seal — success / quality marker (approx. 128px wide)
            </p>
            <MealCraftLogo variant="minimal-animated" width={128} />
        </div>
    ),
};

/** @type {import('@storybook/react-vite').StoryObj} */
export const SmartAnimated = {
    render: () => (
        <div className="flex flex-col gap-4 p-6">
            <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                Seal + wordmark + Smart Kitchen (tagline opacity 0.6)
            </p>
            <MealCraftLogo variant="smart-animated" width={219} />
        </div>
    ),
};

/** @type {import('@storybook/react-vite').StoryObj} */
export const MarketingAnimated = {
    render: () => (
        <div className="flex flex-col gap-4 p-6">
            <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                Full horizontal messaging — inline presentation (not fullscreen splash)
            </p>
            <MealCraftLogo variant="marketing-animated" width={320} />
        </div>
    ),
};

/** Full-bleed dark canvas — matches pre-login splash visual without fixed overlay. */
/** @type {import('@storybook/react-vite').StoryObj} */
export const SplashScreenPreview = {
    decorators: [
        (Story) => (
            <div className="flex min-h-[100dvh] w-full flex-col items-center justify-center bg-[#1C2416] px-4 py-12">
                <div className="w-full max-w-[min(430px,100%)]">
                    <Story />
                </div>
            </div>
        ),
    ],
    render: () => <MealCraftLogo variant="marketing-animated" width={430} />,
};
