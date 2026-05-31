import { GenderOptionCard, genderOptionIcon } from './GenderOptionCard.jsx';

export default {
    title: 'MealCraft/Molecules/Onboarding/GenderOptionCard',
    component: GenderOptionCard,
    parameters: {
        docs: {
            description: {
                component: 'Selectable gender card used in the customer onboarding profile step.',
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
        <div className="mx-auto grid w-full max-w-[280px] grid-cols-2 gap-3 sm:max-w-xs sm:gap-4">
            <GenderOptionCard label="Male" selected icon={genderOptionIcon('male')} className="w-full" />
            <GenderOptionCard label="Female" icon={genderOptionIcon('female')} className="w-full" />
        </div>
    ),
};
