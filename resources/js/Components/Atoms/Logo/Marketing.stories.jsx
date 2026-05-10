import MealCraftLogo from './MealCraftLogo.jsx';
import { mealCraftLogoPageDecorator } from './logoStoryDecorators.jsx';

/** Isolated marketing tier — horizontal + vertical lockups. */
/** @type {import('@storybook/react-vite').Meta} */
const meta = {
    title: 'MealCraft/Identity/All Variants/Marketing',
    component: MealCraftLogo,
    decorators: [mealCraftLogoPageDecorator],
    parameters: {
        controls: { disable: true },
    },
};

export default meta;

/** @type {import('@storybook/react-vite').StoryObj} */
export const MarketingAnimated = {
    render: () => (
        <div className="flex max-w-6xl flex-col gap-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">marketing-animated · full tagline (opacity 0.6)</p>
            <MealCraftLogo variant="marketing-animated" width={320} />
        </div>
    ),
};

/** @type {import('@storybook/react-vite').StoryObj} */
export const BothOrientations = {
    render: () => (
        <div className="flex max-w-6xl flex-col gap-12 lg:flex-row lg:items-start lg:gap-16">
            <div className="flex min-w-0 flex-col gap-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    Horizontal · 387×130
                </p>
                <MealCraftLogo variant="marketing" width={320} />
            </div>
            <div className="flex min-w-0 flex-col gap-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    Vertical · 400×380
                </p>
                <MealCraftLogo variant="vertical-marketing" width={400} className="h-auto max-w-full" />
            </div>
        </div>
    ),
};
