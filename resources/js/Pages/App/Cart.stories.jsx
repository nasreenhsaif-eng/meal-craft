import { CartInner } from './Cart.jsx';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

export default {
    title: 'Design System/06. Pages/App/Cart',
    component: CartInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'Empty shopping cart with Close back to home.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    name: 'Empty cart',
    render: () => <CartInner customerName="Nasreen" />,
};