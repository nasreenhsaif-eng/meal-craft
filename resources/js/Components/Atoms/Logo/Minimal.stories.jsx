import MealCraftLogo from './MealCraftLogo.jsx';
import { mealCraftLogoPageDecorator } from './logoStoryDecorators.jsx';

/** Isolated minimal tier — horizontal + vertical lockups (sprite slices from MealCraftLogo). */

/** @type {import('@storybook/react-vite').Meta} */
const meta = {
    title: 'MealCraft/Identity/All Variants/Minimal',
    component: MealCraftLogo,
    decorators: [mealCraftLogoPageDecorator],
    parameters: {
        controls: { disable: true },
    },
};

export default meta;

/** @type {import('@storybook/react-vite').StoryObj} */
export const MinimalAnimated = {
    render: () => (
        <div className="flex max-w-6xl flex-col gap-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">minimal-animated · Motion seal</p>
            <MealCraftLogo variant="minimal-animated" width={128} />
        </div>
    ),
};

/** @type {import('@storybook/react-vite').StoryObj} */
export const BothOrientations = {
    render: () => (
        <div className="flex max-w-6xl flex-col gap-12 lg:flex-row lg:items-start lg:gap-16">
            <div className="flex min-w-0 flex-col gap-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    Horizontal · 128×30
                </p>
                <MealCraftLogo variant="minimal" width={128} />
            </div>
            <div className="flex min-w-0 flex-col gap-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    Vertical · 280×180
                </p>
                <MealCraftLogo variant="vertical-minimal" width={280} className="h-auto max-w-full" />
            </div>
        </div>
    ),
};
