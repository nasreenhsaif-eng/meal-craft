import DairyFreeFilterNotice from './DairyFreeFilterNotice.jsx';

export default {
    title: 'Design System/03. Molecules/Form and pickers/DairyFreeFilterNotice',
    component: DairyFreeFilterNotice,
};

export const Default = {
    render: () => (
        <div className="mx-auto max-w-xl p-4">
            <DairyFreeFilterNotice onDismiss={() => {}} />
        </div>
    ),
};
