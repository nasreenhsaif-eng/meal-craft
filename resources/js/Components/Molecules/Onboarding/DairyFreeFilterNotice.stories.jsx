import DairyFreeFilterNotice from './DairyFreeFilterNotice.jsx';
import CustomerOnboardingPhoneFrame from '../Customer/CustomerOnboardingPhoneFrame.jsx';

export default {
    title: 'Design System/03. Molecules/Form and pickers/DairyFreeFilterNotice',
    component: DairyFreeFilterNotice,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Modal note shown inside the onboarding phone frame when the customer selects Dairy.',
            },
        },
    },
};

export const Default = {
    name: 'Modal in phone frame',
    render: () => (
        <CustomerOnboardingPhoneFrame storybook>
            <div className="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-2">
                <p className="font-body text-sm text-[#555555]">Food filters sit behind the dairy note.</p>
                <DairyFreeFilterNotice onDismiss={() => {}} />
            </div>
        </CustomerOnboardingPhoneFrame>
    ),
};
