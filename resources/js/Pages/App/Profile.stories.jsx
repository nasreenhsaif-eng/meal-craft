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

export default {
    title: 'Design System/05. Templates & Pages/App/Profile',
    component: ProfileInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Contact-only profile with Edit to checkout details; Sign out at the bottom; customer app bottom nav (Profile active). Body & plan lives under My Tools.',
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
            contactEditUrl="/checkout/details"
            logoutUrl="/logout"
        />
    ),
};
