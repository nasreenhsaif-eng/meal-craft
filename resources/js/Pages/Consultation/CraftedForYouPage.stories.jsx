import { useState } from 'react';
import CraftedForYouPage, { consultationMeals } from './CraftedForYouPage.jsx';
import { consultationDeckOptionsForSlotKey } from '../../Components/Consultation/ChooseYourMeals.jsx';
import StackedDeckCarousel from '../../Components/MealCard/StackedDeckCarousel.jsx';
import MealCardClientViewNano from '../../Components/MealCardClientViewNano.jsx';

const mealOptionsDemo = consultationDeckOptionsForSlotKey(consultationMeals, 'meal');

export default {
    title: 'MealCraft/Pages/Admin/Consultation/CraftedForYou',
    component: CraftedForYouPage,
    parameters: {
        layout: 'fullscreen',
        a11y: { config: { rules: [{ id: 'color-contrast', enabled: true }] } },
    },
};

export const Default = {
    render: () => <CraftedForYouPage disableAdaptedMenuFetch />,
};

/**
 * Isolated meal ribbon — same 4 capped mains as production consultation decks.
 */
export const StackedDeckConsultationMeals = {
    render: () => {
        const [selectedIds, setSelectedIds] = useState(/** @type {string[]} */ ([]));

        const toggle = (id) => {
            setSelectedIds((prev) => {
                if (prev.includes(id)) {
                    return prev.filter((x) => x !== id);
                }
                if (prev.length >= 2) {
                    return prev;
                }
                return [...prev, id];
            });
        };

        return (
            <div className="min-h-screen bg-[#F8F9F6] p-6">
                <p className="mb-4 font-montserrat text-sm font-bold text-[#262A22]">
                    Meals of the Day deck — 4 options, select exactly 2 (mock fixtures)
                </p>
                <div className="max-w-5xl rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm">
                    <StackedDeckCarousel
                        title=""
                        meals={mealOptionsDemo}
                        deckScopeKey="story-stacked-deck-consultation"
                        getKey={(m) => m.id}
                        renderCard={(m, _idx, { isFront, deckLayout }) => {
                            const isSelected = selectedIds.includes(m.id);
                            const atLimit = selectedIds.length >= 2;

                            return (
                                <MealCardClientViewNano
                                    deck
                                    ribbon={deckLayout === 'ribbon'}
                                    alignActionsBottom={deckLayout === 'staticPair'}
                                    deckStackRole={isFront ? 'front' : 'back'}
                                    title={m.title}
                                    imageUrl={m.imageUrl}
                                    imageAlt={m.title}
                                    macros={m.macros}
                                    selected={isSelected}
                                    disabled={!isSelected && atLimit}
                                    vibrantCraftWhenAtLimit={!isSelected && atLimit}
                                    imageLoading={isFront ? 'eager' : 'lazy'}
                                    onToggleSelected={() => toggle(m.id)}
                                    onViewDetails={() => {}}
                                />
                            );
                        }}
                    />
                </div>
            </div>
        );
    },
};
