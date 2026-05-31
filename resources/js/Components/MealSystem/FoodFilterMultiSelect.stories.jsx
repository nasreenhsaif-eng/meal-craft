import { useState } from 'react';
import FoodFilterMultiSelect from './FoodFilterMultiSelect.jsx';
import { FOOD_FILTER_OTHER_ID } from './foodFilterOptions.js';

export default {
    title: 'MealCraft/Meal System/FoodFilterMultiSelect',
    component: FoodFilterMultiSelect,
    parameters: {
        layout: 'padded',
        docs: {
            description: {
                component:
                    'Multi-select food filters using the same Pure pill styling as DietaryTags. Shows a text input when Other is selected.',
            },
        },
    },
};

export const Default = {
    render: () => {
        const [value, setValue] = useState([]);
        const [otherText, setOtherText] = useState('');

        return (
            <div className="max-w-2xl bg-white p-8">
                <FoodFilterMultiSelect
                    value={value}
                    onChange={setValue}
                    otherText={otherText}
                    onOtherTextChange={setOtherText}
                />
            </div>
        );
    },
};

export const WithOtherSelected = {
    name: 'Other selected',
    render: () => {
        const [value, setValue] = useState([FOOD_FILTER_OTHER_ID, 'dairy']);
        const [otherText, setOtherText] = useState('Cilantro, raw onion');

        return (
            <div className="max-w-2xl bg-white p-8">
                <FoodFilterMultiSelect
                    value={value}
                    onChange={setValue}
                    otherText={otherText}
                    onOtherTextChange={setOtherText}
                />
            </div>
        );
    },
};

export const MultiSelectPreview = {
    name: 'Multi-select preview',
    render: () => (
        <div className="max-w-2xl bg-white p-8">
            <FoodFilterMultiSelect value={['gluten', 'eggs', 'beans', 'spicy']} otherText="" />
        </div>
    ),
};
