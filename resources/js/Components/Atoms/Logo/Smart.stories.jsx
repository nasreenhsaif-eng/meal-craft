import MealCraftLogo from './MealCraftLogo.jsx';
import { mealCraftLogoPageDecorator } from './logoStoryDecorators.jsx';

/** Isolated smart tier — horizontal + vertical lockups. */

/** @type {import('@storybook/react-vite').Meta} */
const meta = {
    title: 'MealCraft/Identity/All Variants/Smart',
    component: MealCraftLogo,
    decorators: [mealCraftLogoPageDecorator],
    parameters: {
        controls: { disable: true },
    },
};

export default meta;

/** Motion smart tier — SMART KITCHEN tagline (0.6 opacity), vein clip + typography finalized in `AnimatedSeal` / `MealCraftLogoAnimated`. */
/** @type {import('@storybook/react-vite').StoryObj} */
export const SmartAnimated = {
    render: () => (
        <div className="flex max-w-6xl flex-col gap-3">
            <p className="max-w-xl text-xs font-semibold uppercase tracking-wide text-neutral-500">
                smart-animated · Baloo wordmark + SMART KITCHEN (larger, all caps, 60% green on light canvas)
            </p>
            <MealCraftLogo variant="smart-animated" width={219} />
        </div>
    ),
};

/**
 * Static horizontal/vertical use the **sprite** (`logo-sheet.svg`) — tagline shape/weight is baked in the asset.
 * Compare alongside `SmartAnimated` to review Motion vs raster.
 */
/** @type {import('@storybook/react-vite').StoryObj} */
export const BothOrientations = {
    render: () => (
        <div className="flex max-w-6xl flex-col gap-12 lg:flex-row lg:items-start lg:gap-16">
            <div className="flex min-w-0 flex-col gap-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    Horizontal · 217×68 (sprite)
                </p>
                <MealCraftLogo variant="smart" width={217} />
            </div>
            <div className="flex min-w-0 flex-col gap-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    Vertical · 280×220 (sprite)
                </p>
                <MealCraftLogo variant="vertical-smart" width={280} className="h-auto max-w-full" />
            </div>
        </div>
    ),
};
