import DietaryTags, { DIETARY_TAG_OPTIONS } from '../MealSystem/DietaryTags.jsx';

export default {
    title: 'MealCraft/Atoms/Meal System/DietaryTags',
    component: DietaryTags,
    parameters: { layout: 'padded' },
};

export const CoreSet = {
    name: 'Core set',
    render: () => (
        <div className="max-w-2xl bg-white p-8">
            <DietaryTags
                tags={['Gluten-free', 'Vegan', 'Vegetarian', 'Nut-free', 'Spicy', 'High Protein', 'Low Carbs']}
            />
        </div>
    ),
};

export const PureLayout = {
    name: 'Pure layout (all tags)',
    render: () => (
        <div className="max-w-5xl bg-white p-8">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {DIETARY_TAG_OPTIONS.map((t) => (
                    <div key={t} className="flex items-center">
                        <DietaryTags tags={[t]} />
                    </div>
                ))}
            </div>
        </div>
    ),
};

