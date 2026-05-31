import FoodFilterPill from './FoodFilterPill.jsx';
import { IconDairy } from './FoodFilterIcons.jsx';
import { FOOD_FILTER_OPTIONS } from './foodFilterOptions.js';

export default {
    title: 'MealCraft/Meal System/FoodFilterPill',
    component: FoodFilterPill,
    argTypes: {
        label: { control: 'text' },
        isActive: { control: 'boolean' },
    },
    parameters: {
        layout: 'padded',
    },
};

export const Playground = {
    args: {
        label: 'Dairy',
        isActive: false,
    },
    render: (args) => <FoodFilterPill {...args} icon={<IconDairy />} />,
};

export const Active = {
    args: {
        label: 'Dairy',
        isActive: true,
    },
    render: (args) => <FoodFilterPill {...args} icon={<IconDairy />} />,
};

export const AllergenGallery = {
    name: 'Allergen options',
    render: () => (
        <div className="max-w-2xl bg-white p-8">
            <div className="flex flex-wrap justify-center gap-3">
                {FOOD_FILTER_OPTIONS.map((option, index) => (
                    <FoodFilterPill
                        key={option.id}
                        label={option.label}
                        icon={<option.Icon />}
                        isActive={index % 2 === 0}
                        onClick={() => undefined}
                    />
                ))}
            </div>
        </div>
    ),
};
