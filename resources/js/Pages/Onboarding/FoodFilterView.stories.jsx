import { OnboardingFoodFilterInner } from './FoodFilter.jsx';
import { FOOD_FILTER_OTHER_ID } from '../../Components/MealSystem/foodFilterOptions.js';

const ONBOARDING_STEPS = [
    { value: 'welcome', label: 'Welcome' },
    { value: 'gender', label: 'Gender' },
    { value: 'birthday', label: 'Birthday' },
    { value: 'height', label: 'Height' },
    { value: 'weight', label: 'Weight' },
    { value: 'target_weight', label: 'Target weight' },
    { value: 'activity', label: 'Activity' },
    { value: 'food_filters', label: 'Food filters' },
    { value: 'macros', label: 'Macro split' },
    { value: 'meals', label: 'Choose meals' },
    { value: 'review', label: 'Review' },
];

export default {
    title: 'MealCraft/Pages/Onboarding/FoodFilterView',
    component: OnboardingFoodFilterInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Customer onboarding food filter step — multi-select dietary tags with dynamic Other text input.',
            },
        },
    },
};

export const Default = {
    render: () => (
        <OnboardingFoodFilterInner
            steps={ONBOARDING_STEPS}
            currentStep="food_filters"
            customerName="Amina Saif"
        />
    ),
};

export const WithSelections = {
    name: 'With selections',
    render: () => (
        <OnboardingFoodFilterInner
            selectedFilters={['gluten', 'beans', 'spicy']}
            otherText=""
            steps={ONBOARDING_STEPS}
            currentStep="food_filters"
            customerName="Amina Saif"
        />
    ),
};

export const WithOtherSelected = {
    name: 'Other selected',
    render: () => (
        <OnboardingFoodFilterInner
            selectedFilters={[FOOD_FILTER_OTHER_ID, 'dairy']}
            otherText="Cilantro, raw onion"
            steps={ONBOARDING_STEPS}
            currentStep="food_filters"
            customerName="Amina Saif"
        />
    ),
};
