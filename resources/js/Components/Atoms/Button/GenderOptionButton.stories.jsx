import { useState } from 'react';
import { GenderOptionButton, genderOptionIcon } from './GenderOptionButton.jsx';

/**
 * Onboarding content width. Do not use `max-w-md` — `--spacing-md` is 16px in this theme,
 * so that utility caps the stack at 16px and the pair collapses.
 */
const ONBOARDING_STACK = 'w-full max-w-[28rem]';

export default {
    title: 'Design System/02. Atoms/Button/GenderOptionButton',
    component: GenderOptionButton,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Male and female choices. Same pale-green selection button as Atoms / Button / OnboardingOptionButton.',
            },
        },
    },
    decorators: [
        (Story) => (
            <div className="flex min-h-screen w-full justify-center bg-white px-6 py-10">
                <div className={ONBOARDING_STACK}>
                    <Story />
                </div>
            </div>
        ),
    ],
    argTypes: {
        onSelect: { action: 'selected' },
    },
};

export const Male = {
    args: {
        label: 'Male',
        selected: false,
        icon: genderOptionIcon('male'),
    },
};

export const MaleSelected = {
    name: 'Male (selected)',
    args: {
        label: 'Male',
        selected: true,
        icon: genderOptionIcon('male'),
    },
};

export const Female = {
    args: {
        label: 'Female',
        selected: false,
        icon: genderOptionIcon('female'),
    },
};

export const FemaleSelected = {
    name: 'Female (selected)',
    args: {
        label: 'Female',
        selected: true,
        icon: genderOptionIcon('female'),
    },
};

export const Pair = {
    render: function PairStory() {
        const [sex, setSex] = useState('male');

        return (
            <div className="flex w-full flex-col gap-3">
                <GenderOptionButton
                    label="Male"
                    selected={sex === 'male'}
                    icon={genderOptionIcon('male')}
                    onSelect={() => setSex('male')}
                />
                <GenderOptionButton
                    label="Female"
                    selected={sex === 'female'}
                    icon={genderOptionIcon('female')}
                    onSelect={() => setSex('female')}
                />
            </div>
        );
    },
};
