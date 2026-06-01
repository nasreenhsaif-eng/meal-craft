import { OnboardingFoodFilterInner } from './FoodFilter.jsx';
import { FOOD_FILTER_OTHER_ID } from '../../Components/MealSystem/foodFilterOptions.js';
import { ONBOARDING_STEPS } from './onboardingSteps.js';

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
