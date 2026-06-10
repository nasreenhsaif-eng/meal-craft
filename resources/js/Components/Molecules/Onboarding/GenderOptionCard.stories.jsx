import { GenderOptionCard, genderOptionIcon } from './GenderOptionCard.jsx';

export default {
    title: 'MealCraft/Molecules/Onboarding/GenderOptionCard',
    component: GenderOptionCard,
    parameters: {
        docs: {
            description: {
                component: 'Full-width secondary gender choice with inline icon and label.',
            },
        },
    },
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
    render: () => (
        <div className="w-full max-w-md px-4">
            <div className="flex w-full flex-col gap-3 [&_.mc-gender-option]:w-full">
                <GenderOptionCard label="Male" selected icon={genderOptionIcon('male')} />
                <GenderOptionCard label="Female" icon={genderOptionIcon('female')} />
            </div>
        </div>
    ),
};
