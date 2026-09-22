import { useState } from 'react';
import ProtocolFixedChoiceSides from './ProtocolFixedChoiceSides.jsx';
import { consultationMeals } from '../../consultation/consultationMockMeals.js';
import { applyFixedChoiceToggle } from './ChooseYourMeals.jsx';
import { withConsultationMobileFrame } from '../../Pages/Consultation/consultationStoryDecorators.jsx';

const sideSalads = consultationMeals.filter((m) => String(m.mealType).toLowerCase().includes('side'));
const desserts = consultationMeals.filter((m) => String(m.mealType).toLowerCase() === 'dessert');
const soups = consultationMeals.filter((m) => String(m.mealType).toLowerCase() === 'soup');

const displayDecks = {
    sideSalads,
    desserts,
    soup: soups,
};

export default {
    title: 'Design System/05. Templates & Pages/Consultation/ProtocolFixedChoiceSides',
    component: ProtocolFixedChoiceSides,
    decorators: withConsultationMobileFrame,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Pick 1–2 of side salad / dessert / soup — used in Full Craft nutrient-dense and fixed-choice flows.',
            },
        },
    },
};

export const Interactive = {
    name: 'Interactive picker',
    render: () => {
        const [categorySelections, setCategorySelections] = useState({
            sideSalads: [],
            desserts: [],
            soup: [],
        });

        return (
            <div className="w-full px-3 py-4 sm:mx-auto sm:max-w-[28rem] sm:px-6 sm:py-8">
                <ProtocolFixedChoiceSides
                    categorySelections={categorySelections}
                    displayDecks={displayDecks}
                    onSelectMeal={(categoryKey, meal) => {
                        setCategorySelections((prev) => {
                            const { next, blocked } = applyFixedChoiceToggle(prev, categoryKey, meal.id);

                            return blocked ? prev : next;
                        });
                    }}
                    onClearCategory={(categoryKey) => {
                        setCategorySelections((prev) => ({ ...prev, [categoryKey]: [] }));
                    }}
                    onSeeOtherOptions={() => {}}
                    onViewDetails={() => {}}
                />
            </div>
        );
    },
};

export const WithSelections = {
    name: 'With two sides selected',
    render: () => (
        <div className="w-full px-3 py-4 sm:mx-auto sm:max-w-[28rem] sm:px-6 sm:py-8">
            <ProtocolFixedChoiceSides
                categorySelections={{
                    sideSalads: sideSalads[0] ? [String(sideSalads[0].id)] : [],
                    desserts: desserts[0] ? [String(desserts[0].id)] : [],
                    soup: [],
                }}
                displayDecks={displayDecks}
                onSelectMeal={() => {}}
                onClearCategory={() => {}}
                onViewDetails={() => {}}
            />
        </div>
    ),
};
