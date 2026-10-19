import { ProfileInner } from './Profile.jsx';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

const NASREEN_DETAILS = {
    name: 'Nasreen',
    email: 'nasreen@example.com',
    phone: '+973 3317 7718',
    address: 'Adliya, Bahrain',
    deliveryAddress: 'House 12, Gate/Flat 4, Road 1801, Block 318, Adliya, Bahrain',
    cards: [],
    sexLabel: 'Female',
    dietProtocolLabel: 'Balanced Anti-inflammatory',
    dateOfBirth: '1990-05-12',
    age: 34,
    heightCm: 165,
    weightKg: 68,
    targetWeightKg: 64,
    activityLevelLabel: 'Somewhat active',
    dailyCaloriesMin: 1575,
    dailyCaloriesMax: 1825,
    macroSplitStyle: 'balanced',
    foodFilters: ['peanuts', 'shellfish'],
    allergies: ['peanuts', 'shellfish'],
};

export default {
    title: 'Design System/05. Templates & Pages/App/Profile',
    component: ProfileInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Read-only Your details screen with Body & plan then Contact frames; Edit on Body & plan opens onboarding; Sign out at the bottom; customer app bottom nav (Profile active).',
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
            profileEditUrl="/onboarding/gender"
            logoutUrl="/logout"
        />
    ),
};
