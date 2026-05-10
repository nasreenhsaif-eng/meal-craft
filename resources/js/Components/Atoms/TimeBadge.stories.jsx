import TimeBadge from '../MealSystem/TimeBadge.jsx';

export default {
    title: 'MealCraft/Atoms/Meal System/TimeBadge',
    component: TimeBadge,
    parameters: { layout: 'padded' },
    argTypes: {
        minutes: { control: { type: 'number', min: 0, step: 1 } },
    },
};

export const Playground = {
    args: { minutes: 25 },
    render: (args) => <TimeBadge {...args} />,
};

