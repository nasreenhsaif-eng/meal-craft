import { FulfillmentSelectionInner } from './FulfillmentSelection.jsx';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

export default {
    title: 'Design System/06. Pages/Checkout/FulfillmentSelection',
    component: FulfillmentSelectionInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Checkout fulfillment step — Partner Kitchen delivery vs DIY recipes after plan selection.',
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