import CategoryBadge from './CategoryBadges.jsx';

export default {
    title: 'Design System/02. Atoms/Badge/CategoryBadges',
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

