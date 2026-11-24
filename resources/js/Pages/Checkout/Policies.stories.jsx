import Policies from './Policies.jsx';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

export default {
    title: 'Design System/06. Pages/Checkout/Policies',
    component: Policies,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'Freeze and refund policy copy linked from payment checkout.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    name: 'Policies',
    render: () => <Policies customerName="Nasreen" />,
};