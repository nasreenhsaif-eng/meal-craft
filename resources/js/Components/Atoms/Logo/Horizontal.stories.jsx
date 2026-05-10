import MealCraftLogo, { mealCraftLogoArgTypes } from './MealCraftLogo.jsx';
import { mealCraftLogoHorizontalStoryDecorator } from './logoStoryDecorators.jsx';

/** @type {import('@storybook/react-vite').Meta} */
const meta = {
    title: 'MealCraft/Identity/Horizontal Lockups',
    component: MealCraftLogo,
    decorators: [mealCraftLogoHorizontalStoryDecorator],
    argTypes: mealCraftLogoArgTypes,
};

export default meta;

/** @type {import('@storybook/react-vite').StoryObj} */
export const Minimal = {
    render: () => <MealCraftLogo variant="minimal" width={128} />,
};

/** @type {import('@storybook/react-vite').StoryObj} */
export const Smart = {
    render: () => <MealCraftLogo variant="smart" width={217} />,
};

/** @type {import('@storybook/react-vite').StoryObj} */
export const Marketing = {
    render: () => <MealCraftLogo variant="marketing" width={320} />,
};
