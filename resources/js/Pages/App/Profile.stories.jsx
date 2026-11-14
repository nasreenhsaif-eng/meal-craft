import { ProfileInner } from './Profile.jsx';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

const NASREEN_DETAILS = {
    name: 'Nasreen',
    email: 'nasreen@example.com',
    phone: '+973 3317 7718',
    address: 'Adliya, Bahrain',
    deliveryAddress: 'House 12, Gate/Flat 4, Road 1801, Block 318, Adliya, Bahrain',
    cards: [],
};

const NASREEN_SUBSCRIPTION = {
    mealPlanName: 'Full Craft',
    days: 28,
    startDate: '2026-11-15',
    expiryDate: '2026-12-12',
    deliveries: 28,
};

export default {
    title: 'Design System/05. Templates & Pages/App/Profile',
    component: ProfileInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Profile with Contact and Subscriptions under Your details; Edit opens checkout details; Sign out at the bottom; customer app bottom nav (Profile active). Body & plan lives under My Tools.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    name: 'Your details',
    render: () => (
        <ProfileInner
            customerName="Nasreen"
            details={NASREEN_DETAILS}
            subscription={NASREEN_SUBSCRIPTION}
            contactEditUrl="/checkout/details"
            logoutUrl="/logout"
        />
    ),
};
