import { ShopInner } from './Shop.jsx';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

export default {
    title: 'Design System/06. Pages/App/Shop',
    component: ShopInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'Partner kitchen shop placeholder with customer app chrome and Shop tab active.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    name: 'Shop',
    render: () => <ShopInner customerName="Nasreen" />,
};