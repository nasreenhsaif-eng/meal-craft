import CategoryBadge from '../MealSystem/CategoryBadges.jsx';

export default {
    title: 'MealCraft/Atoms/Meal System/CategoryBadges',
    component: CategoryBadge,
    parameters: { layout: 'padded' },
    argTypes: {
        variant: { control: 'select', options: ['breakfast', 'meal', 'dessert', 'sideSalad', 'soup'] },
        label: { control: 'text' },
    },
};

export const Playground = {
    args: { variant: 'meal', label: '' },
    render: (args) => <CategoryBadge {...args} />,
};

