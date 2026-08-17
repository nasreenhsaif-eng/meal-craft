import DairyFreeFilterNotice from './DairyFreeFilterNotice.jsx';

export default {
    title: 'MealCraft/Molecules/Onboarding/DairyFreeFilterNotice',
    component: DairyFreeFilterNotice,
};

export const Default = {
    render: () => (
        <div className="mx-auto max-w-xl p-4">
            <DairyFreeFilterNotice onDismiss={() => {}} />
        </div>
    ),
};
