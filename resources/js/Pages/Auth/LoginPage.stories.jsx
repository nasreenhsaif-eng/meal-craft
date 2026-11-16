import LoginPage from './LoginPage';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

export default {
    title: 'Design System/05. Templates & Pages/Auth/LoginPage',
    component: LoginPage,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Sign-in form in the same phone frame as landing and onboarding. Static `seal-md` mark; after success, `marketing-animated` seal plays then redirects.\n\n**Smart login redirect (server):** admins/staff → portal choice; incomplete onboarding → `/onboarding/gender`; finished onboarding → Welcome Back (`/app`).',
            },
        },
    },
};

export const Default = {
    name: 'Default',
    render: () => <LoginPage />,
};

/** White post-auth seal — plays after login / signup verify, before redirect. */
export const SuccessSeal = {
    name: 'Success Seal',
    decorators: withOnboardingMobileFrame,
    parameters: {
        docs: {
            description: {
                story: 'Fullscreen white overlay with `marketing-animated` seal after successful auth (login or signup OTP). Not the dark Logos/Animated splash.',
            },
        },
    },
    render: () => <LoginPage previewSuccessSeal />,
};
