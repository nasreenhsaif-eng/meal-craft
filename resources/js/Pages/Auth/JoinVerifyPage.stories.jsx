import JoinVerifyPage from './JoinVerifyPage';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

export default {
    title: 'Design System/06. Pages/Auth/JoinVerifyPage',
    component: JoinVerifyPage,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Signup OTP step — code sent to email and WhatsApp. Same phone frame as Join / Login; success plays the marketing seal before redirect.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    name: 'Enter verification code',
    render: () => (
        <JoinVerifyPage
            email="nasreen@example.com"
            maskedPhone="+973 •••• 7718"
            previewCode="123456"
        />
    ),
};

export const WithError = {
    name: 'Invalid code',
    render: () => (
        <JoinVerifyPage
            email="nasreen@example.com"
            maskedPhone="+973 •••• 7718"
            codeError="That code is incorrect or expired."
        />
    ),
};