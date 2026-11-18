import { FulfillmentSelectionInner } from '../Checkout/FulfillmentSelection.jsx';
import { withOnboardingMobileFrame } from './onboardingStoryDecorators.jsx';

export default {
    title: 'Design System/05. Templates & Pages/Onboarding/FulfillmentSelection',
    component: FulfillmentSelectionInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Receive meal plan choice (Partner Kitchen / DIY). In consultation this step follows manual day selection; `/checkout` keeps the same UI for post-summary delivery.',
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
