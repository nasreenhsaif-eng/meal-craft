import { useState } from 'react';
import { DIET_PROTOCOL_OPTIONS } from '../../Molecules/Onboarding/dietProtocolOptions.js';
import OnboardingOptionButton from './OnboardingOptionButton.jsx';

const STACK = 'w-full max-w-[28rem]';

export default {
    title: 'Design System/02. Atoms/Button/OnboardingOptionButton',
    component: OnboardingOptionButton,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Full-width washed-green selection button (uppercase). Used for diet protocol, activity, gender, and craft. Pair with OnboardingInlineDescription under the selected row.',
            },
        },
    },
    decorators: [
        (Story) => (
            <div className="flex min-h-screen w-full justify-center bg-[#F8F9F6] px-6 py-10">
                <div className={STACK}>
                    <Story />
                </div>
            </div>
        ),
    ],
};

export const ProtocolList = {
    name: 'Diet protocol options',
    render: function ProtocolListStory() {
        const [selected, setSelected] = useState('nutrient_dense');

        return (
            <div className="flex w-full flex-col gap-2.5">
                {DIET_PROTOCOL_OPTIONS.map((option) => (
                    <OnboardingOptionButton
                        key={option.id}
                        label={option.label}
                        selected={selected === option.id}
                        icon={<option.Icon />}
                        onSelect={() => setSelected(option.id)}
                    />
                ))}
            </div>
        );
    },
};

export const Selected = {
    args: {
        label: 'Nutrient Density Protocol',
        selected: true,
    },
};

export const Unselected = {
    args: {
        label: 'Balanced Protocol',
        selected: false,
    },
};
