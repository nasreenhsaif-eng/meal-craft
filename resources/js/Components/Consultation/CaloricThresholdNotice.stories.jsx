import CaloricThresholdNotice from './CaloricThresholdNotice.jsx';
import { withConsultationMobileFrame } from '../../Pages/Consultation/consultationStoryDecorators.jsx';

export default {
    title: 'Design System/05. Templates & Pages/Crafted for you/CaloricThresholdNotice',
    component: CaloricThresholdNotice,
    decorators: withConsultationMobileFrame,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Amber notice shown when the plan tier is below the micronutrient-enforcement calorie threshold.',
            },
        },
    },
};

export const BelowThreshold = {
    name: 'Below threshold (1200 kcal)',
    render: () => (
        <div className="w-full px-3 py-4 sm:mx-auto sm:max-w-[40rem] sm:px-6 sm:py-8">
            <CaloricThresholdNotice planTierCalories={1200} />
        </div>
    ),
};

export const HiddenWhenAbove = {
    name: 'At or above 1500 — renders nothing',
    render: () => (
        <div className="w-full px-3 py-4 sm:mx-auto sm:max-w-[40rem] sm:px-6 sm:py-8">
            <p className="mb-3 font-body text-sm text-[#555555]">
                Expect an empty canvas when the plan is at or above the micronutrient floor (1500 kcal).
            </p>
            <CaloricThresholdNotice planTierCalories={1500} />
        </div>
    ),
};
