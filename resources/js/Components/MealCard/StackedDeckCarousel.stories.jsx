import { useState } from 'react';
import StackedDeckCarousel from './StackedDeckCarousel.jsx';
import MealCardClientViewNano from '../MealCardClientViewNano.jsx';

const MEALS = [
    {
        id: 'm1',
        title: 'Post-workout salmon bowl',
        imageUrl: 'https://images.unsplash.com/photo-1553621042-f6e147245754?auto=format&fit=crop&w=1400&q=80',
        macros: { calories: 520, protein: '44g', carbs: '38g', fat: '22g' },
    },
    {
        id: 'm2',
        title: 'Herb chicken quinoa plate',
        imageUrl: 'https://images.unsplash.com/photo-1604908177225-6b9dd98ec605?auto=format&fit=crop&w=1400&q=80',
        macros: { calories: 610, protein: '48g', carbs: '58g', fat: '19g' },
    },
    {
        id: 'm3',
        title: 'Ketogenic steak + greens',
        imageUrl: 'https://images.unsplash.com/photo-1551183053-bf91a1d81141?auto=format&fit=crop&w=1400&q=80',
        macros: { calories: 720, protein: '55g', carbs: '14g', fat: '46g' },
    },
    {
        id: 'm4',
        title: 'Hormone Feast turkey + sweet potato',
        imageUrl: 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=1400&q=80',
        macros: { calories: 640, protein: '46g', carbs: '62g', fat: '20g' },
    },
];

const DESSERTS = [
    {
        id: 'd1',
        title: 'Dessert — yogurt berries',
        imageUrl: 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?auto=format&fit=crop&w=1400&q=80',
        macros: { calories: 220, protein: '12g', carbs: '24g', fat: '8g' },
    },
    {
        id: 'd2',
        title: 'Dessert — cacao chia mousse',
        imageUrl: 'https://images.unsplash.com/photo-1495147466023-ac5c588e2e94?auto=format&fit=crop&w=1400&q=80',
        macros: { calories: 260, protein: '10g', carbs: '28g', fat: '13g' },
    },
];

function DeckCard({ meal, ctx, selectedId, onToggle }) {
    const { isFront, deckLayout } = ctx;

    return (
        <MealCardClientViewNano
            deck
            ribbon={deckLayout === 'ribbon'}
            deckStackRole={isFront ? 'front' : 'back'}
            title={meal.title}
            imageUrl={meal.imageUrl}
            imageAlt={meal.title}
            macros={meal.macros}
            selected={selectedId === meal.id}
            imageLoading={isFront ? 'eager' : 'lazy'}
            onToggleSelected={() => onToggle(meal.id)}
            onViewDetails={() => {}}
        />
    );
}

export default {
    title: 'MealCraft/Components/MealCard/StackedDeckCarousel',
    component: StackedDeckCarousel,
    parameters: {
        layout: 'padded',
    },
};

/**
 * Consultation “Meals of the Day” ribbon — 4 capped options.
 */
export const ConsultationMealsRibbon = {
    render: () => {
        const [selectedId, setSelectedId] = useState(null);

        return (
            <div className="max-w-5xl bg-white p-6">
                <p className="mb-4 font-montserrat text-sm font-bold text-[#262A22]">
                    Choose Your Meals of the Day — select exactly 2
                </p>
                <StackedDeckCarousel
                    title=""
                    meals={MEALS}
                    deckScopeKey="storybook-consultation-meals-ribbon"
                    getKey={(m) => m.id}
                    renderCard={(m, idx, ctx) => (
                        <DeckCard
                            meal={m}
                            ctx={ctx}
                            selectedId={selectedId}
                            onToggle={(id) => setSelectedId((prev) => (prev === id ? null : id))}
                        />
                    )}
                />
            </div>
        );
    },
};

/**
 * Two-option ribbon (breakfast / side salad / dessert) — same horizontal carousel as mains.
 */
export const TwoOptionRibbon = {
    render: () => {
        const [selectedId, setSelectedId] = useState(null);

        return (
            <div className="max-w-5xl bg-white p-6">
                <p className="mb-4 font-montserrat text-sm font-bold text-[#262A22]">Desserts — select 1</p>
                <StackedDeckCarousel
                    title=""
                    meals={DESSERTS}
                    deckScopeKey="storybook-two-option-ribbon"
                    getKey={(m) => m.id}
                    renderCard={(m, idx, ctx) => (
                        <DeckCard
                            meal={m}
                            ctx={ctx}
                            selectedId={selectedId}
                            onToggle={(id) => setSelectedId((prev) => (prev === id ? null : id))}
                        />
                    )}
                />
            </div>
        );
    },
};

/** @deprecated Use ConsultationMealsRibbon — kept as alias for existing Chromatic baselines. */
export const Default = ConsultationMealsRibbon;

/** @deprecated Use TwoOptionRibbon */
export const StaticPairDesserts = TwoOptionRibbon;
