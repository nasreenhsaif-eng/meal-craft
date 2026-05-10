import MealCraftLogo, { mealCraftLogoArgTypes } from './MealCraftLogo.jsx';
import { mealCraftLogoPageDecorator } from './logoStoryDecorators.jsx';

/** @type {import('@storybook/react-vite').Meta} */
const meta = {
    title: 'MealCraft/Identity/Vertical Lockups',
    component: MealCraftLogo,
    decorators: [mealCraftLogoPageDecorator],
    argTypes: mealCraftLogoArgTypes,
};

export default meta;

/** @type {import('@storybook/react-vite').StoryObj} */
export const Minimal = {
    render: () => (
        <MealCraftLogo variant="vertical-minimal" width={280} className="h-auto max-w-full" />
    ),
};

/** @type {import('@storybook/react-vite').StoryObj} */
export const Smart = {
    render: () => (
        <MealCraftLogo variant="vertical-smart" width={280} className="h-auto max-w-full" />
    ),
};

/** @type {import('@storybook/react-vite').StoryObj} */
export const Marketing = {
    render: () => (
        <MealCraftLogo variant="vertical-marketing" width={400} className="h-auto max-w-full" />
    ),
};
