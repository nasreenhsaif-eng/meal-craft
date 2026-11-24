import Payment from './Payment.jsx';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

const STORY_BASKET = {
    planLabel: 'Full Craft · 28 days',
    averageDayCalories: 1700,
    currency: 'BHD',
    subtotal: 189.5,
    deliveryCost: 0,
    limitedTimeDiscount: 10,
    limitedTimeDiscountLabel: 'Launch offer',
    promoDiscount: 0,
    promoCode: null,
    total: 179.5,
    benefitPayNumber: '12345678',
    refundPolicyUrl: '/checkout/policies#refund',
    freezePolicyUrl: '/checkout/policies#freeze',
    deliveryAddress: {
        formatted: 'House 12, Gate/Flat 4, Road 1801, Block 318, Adliya, Bahrain',
        area: 'Adliya',
        block: '318',
        road: '1801',
        houseNumber: '12',
        gateFlatNumber: '4',
        country: 'Bahrain',
        deliveryTime: 'Morning',
        plannedStartDate: '2026-11-15',
    },
};

export default {
    title: 'Design System/06. Pages/Checkout/Payment',
    component: Payment,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'Payment checkout — cart summary, BenefiPay, promo code, and policy checkboxes.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    name: 'Payment checkout',
    render: () => <Payment customerName="Nasreen" basket={STORY_BASKET} />,
};