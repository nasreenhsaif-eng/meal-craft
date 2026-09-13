import { FulfillmentSelectionInner } from '../Checkout/FulfillmentSelection.jsx';
import { withOnboardingMobileFrame } from './onboardingStoryDecorators.jsx';

export default {
    title: 'MealCraft/Pages/Onboarding/FulfillmentSelection',
    component: FulfillmentSelectionInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Checkout fulfillment choice after the meal-plan dashboard — fresh kitchen delivery or DIY digital recipes.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    name: 'Choose fulfillment',
    render: () => (
        <FulfillmentSelectionInner
            customerName="Nasreen"
            homeUrl="/app"
            deliveryUrl="/checkout/delivery"
            recipesUrl="/meal-plan/recipes"
        />
    ),
};
