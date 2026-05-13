import { useState } from 'react';
import CraftedForYouPage, { consultationMeals } from './CraftedForYouPage.jsx';
import StackedDeckCarousel from '../../Components/MealCard/StackedDeckCarousel.jsx';
import MealCardClientViewNano from '../../Components/MealCardClientViewNano.jsx';

const mealOptionsDemo = consultationMeals.filter((m) => m.mealType === 'Meal').slice(0, 8);

export default {
    title: 'MealCraft/Pages/Admin/Consultation/CraftedForYou',
    component: CraftedForYouPage,
    parameters: {
        layout: 'fullscreen',
        a11y: { config: { rules: [{ id: 'color-contrast', enabled: true }] } },
    },
};

export const Default = {
    render: () => <CraftedForYouPage />,
};

/**
 * Isolated StackedDeckCarousel using the same `consultationMeals` data as the page (for a11y / responsive checks).
 */
export const StackedDeckConsultationMeals = {
    render: () => {
        const [selectedId, setSelectedId] = useState(/** @type {string | null} */ (null));
        return (
            <div className="min-h-screen bg-[#F8F9F6] p-6">
                <p className="mb-4 font-montserrat text-sm font-bold text-[#262A22]">
                    Deck preview — same meal fixtures as consultation page (`consultationMeals`)
                </p>
                <StackedDeckCarousel
                    title=""
                    meals={mealOptionsDemo}
                    deckScopeKey="story-stacked-deck-consultation"
                    getKey={(m) => m.id}
                    renderCard={(m, _idx, { isFront, deckLayout }) => {
                        const isSelected = selectedId === m.id;
                        return (
                            <MealCardClientViewNano
                                deck
                                ribbon={deckLayout === 'ribbon'}
                                deckStackRole={isFront ? 'front' : 'back'}
                                title={m.title}
                                imageUrl={m.imageUrl}
                                imageAlt={m.title}
                                macros={m.macros}
                                selected={isSelected}
                                imageLoading={isFront ? 'eager' : 'lazy'}
                                onToggleSelected={() => setSelectedId((prev) => (prev === m.id ? null : m.id))}
                                onViewDetails={() => {}}
                            />
                        );
                    }}
                />
            </div>
        );
    },
};
