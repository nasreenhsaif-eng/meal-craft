import { useState } from 'react';
import CraftedForYouPage from './CraftedForYouPage.jsx';
import { withConsultationMobileFrame } from './consultationStoryDecorators.jsx';
import { consultationMeals } from '../../consultation/consultationMockMeals.js';
import { consultationDeckOptionsForSlotKey } from '../../Components/Consultation/ChooseYourMeals.jsx';
import StackedDeckCarousel from '../../Components/MealCard/StackedDeckCarousel.jsx';
import MealCardClientViewNano from '../../Components/MealCardClientViewNano.jsx';

const mealOptionsDemo = consultationDeckOptionsForSlotKey(consultationMeals, 'meal');

export default {
    title: 'Design System/05. Templates & Pages/Consultation',
    component: CraftedForYouPage,
    decorators: withConsultationMobileFrame,
    parameters: {
        layout: 'fullscreen',
        a11y: { config: { rules: [{ id: 'color-contrast', enabled: true }] } },
        docs: {
            description: {
                component:
                    'Customer Crafted for YOU consultation: craft type, week duration, day selection, and meal decks with mock library data (no adapted-menu API).',
            },
        },
    },
};

/**
 * Embed mode grows with content (no nested overflow / fixed footers) so Storybook
 * mobile viewports can scroll the document.
 */
export const CraftedForYour = {
    name: 'Crafted for your',
    render: () => (
        <CraftedForYouPage
            disableAdaptedMenuFetch
            embedInScrollParent
            pageEyebrow="Your plan"
        />
    ),
};

/**
 * Isolated meal ribbon — same capped mains as production consultation decks.
 * Story id: design-system-05-templates-pages-consultation--stacked-deck-consultation-meals
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
            <div className="w-full px-3 py-4 sm:px-6 sm:py-6">
                <p className="mb-4 font-montserrat text-sm font-bold text-[#262A22]">
                    Meals of the Day deck — 6 options, select exactly 2 (mock fixtures)
                </p>
                <div className="w-full rounded-[12px] border border-gray-200 bg-white p-3 shadow-sm sm:p-6">
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
