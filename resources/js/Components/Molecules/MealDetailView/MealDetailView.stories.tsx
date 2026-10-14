import MealDetailView from './MealDetailView';
import { mealDetailViewFixture } from './mealDetailViewFixture';

export default {
    title: 'Design System/04. Organisms/DataCard/MealDetailView',
    component: MealDetailView,
    parameters: {
        layout: 'fullscreen',
    },
};

export const ExpandedOvulatoryPhase = {
    name: 'Expanded view (ovulatory phase)',
    render: () => (
        <div className="min-h-screen bg-[#F8F9F6] px-4 py-10 md:px-8">
            <MealDetailView meal={mealDetailViewFixture} />
        </div>
    ),
};
