import NutrientBadge, { NUTRIENT_BADGE_TYPES } from './NutrientBadge.jsx';

export default {
    title: 'MealCraft/Atoms/Meal System/NutrientBadge',
    component: NutrientBadge,
    parameters: { layout: 'padded' },
    argTypes: {
        type: { control: { type: 'select' }, options: NUTRIENT_BADGE_TYPES },
        className: { control: { type: 'text' } },
    },
};

export const Playground = {
    args: { type: 'B12' },
    render: (args) => <NutrientBadge {...args} />,
};

export const AllVariants = {
    name: 'All variants',
    render: () => (
        <div className="flex flex-wrap gap-3 bg-white p-6">
            {NUTRIENT_BADGE_TYPES.map((t) => (
                <NutrientBadge key={t} type={t} />
            ))}
        </div>
    ),
};

