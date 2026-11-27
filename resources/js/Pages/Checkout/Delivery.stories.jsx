import Delivery from './Delivery.jsx';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

export default {
    title: 'Design System/06. Pages/Checkout/Delivery',
    component: Delivery,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'Partner kitchen selection with vertical Picniq card (price chosen earlier in the flow).',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    name: 'Select partner kitchen',
    render: () => (
        <Delivery
            customerName="Nasreen"
            selectedPlanWeeks={4}
            craftTitle="Full Craft"
        />
    ),
};