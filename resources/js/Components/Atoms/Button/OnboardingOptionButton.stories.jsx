import { useState } from 'react';
import { DIET_PROTOCOL_OPTIONS } from '../../Molecules/Onboarding/dietProtocolOptions.js';
import OnboardingOptionButton from './OnboardingOptionButton.jsx';
import { genderOptionIcon } from './GenderOptionButton.jsx';

const STACK = 'w-full max-w-[28rem]';

export default {
    title: 'Design System/02. Atoms/Button/OnboardingOptionButton',
    component: OnboardingOptionButton,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Full-width selection button using the same brand-primary wash as Button `secondary` (`/10` · `/20` · selected `/30`) with a visible brand border. ' +
                    'Used for diet protocol, activity, fulfillment, and craft. ' +
                    '`GenderOptionButton` is the same atom with `align="center"` (Male / Female preset). ' +
                    'Pair with OnboardingInlineDescription under the selected row when needed.',
            },
        },
    },
    decorators: [
        (Story) => (
            <div className="flex min-h-screen w-full justify-center bg-surface-soft px-6 py-10">
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

/** Crafted For You / plan length — price on the right. */
export const WithDetail = {
    name: 'With detail (price)',
    render: function WithDetailStory() {
        const [selected, setSelected] = useState('7');

        return (
            <div className="flex w-full flex-col gap-2.5">
                <OnboardingOptionButton
                    label="7 days"
                    detail="BHD 28.0"
                    selected={selected === '7'}
                    onSelect={() => setSelected('7')}
                />
                <OnboardingOptionButton
                    label="14 days"
                    detail="BHD 52.0"
                    selected={selected === '14'}
                    onSelect={() => setSelected('14')}
                />
            </div>
        );
    },
};

/** Cook It Yourself / Meal Plan Summary day chips — same layout as GenderOptionButton. */
export const AlignCenter = {
    name: 'Align center',
    render: function AlignCenterStory() {
        const [selected, setSelected] = useState(1);

        return (
            <div className="flex w-full flex-col gap-2.5">
                {['Day 1', 'Day 2', 'Day 3'].map((label, index) => (
                    <OnboardingOptionButton
                        key={label}
                        label={label}
                        align="center"
                        selected={selected === index + 1}
                        onSelect={() => setSelected(index + 1)}
                    />
                ))}
            </div>
        );
    },
    parameters: {
        docs: {
            description: {
                story: 'Same centered layout GenderOptionButton uses. Prefer GenderOptionButton for Male / Female.',
            },
        },
    },
};

export const SizeSm = {
    name: 'Size sm',
    args: {
        label: 'Compact option',
        size: 'sm',
        selected: false,
        icon: genderOptionIcon('male'),
    },
};
