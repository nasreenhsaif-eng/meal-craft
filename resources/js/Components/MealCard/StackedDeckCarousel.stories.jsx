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
        title: 'Turmeric lentil soup',
        imageUrl: 'https://images.unsplash.com/photo-1547592166-23ac45744acd?auto=format&fit=crop&w=1400&q=80',
        macros: { calories: 380, protein: '22g', carbs: '52g', fat: '10g' },
    },
    {
        id: 'm4',
        title: 'Dessert — yogurt berries',
        imageUrl: 'https://images.unsplash.com/photo-1499636136210-6f4ee915583e?auto=format&fit=crop&w=1400&q=80',
        macros: { calories: 220, protein: '12g', carbs: '24g', fat: '8g' },
    },
    {
        id: 'm5',
        title: 'Egg white veggie scramble',
        imageUrl: 'https://images.unsplash.com/photo-1525351484163-7529414344d8?auto=format&fit=crop&w=1400&q=80',
        macros: { calories: 312, protein: '28g', carbs: '12g', fat: '16g' },
    },
    {
        id: 'm6',
        title: 'Side salad crunch bowl',
        imageUrl: 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?auto=format&fit=crop&w=1400&q=80',
        macros: { calories: 260, protein: '8g', carbs: '18g', fat: '17g' },
    },
];

export default {
    title: 'MealCraft/Components/MealCard/StackedDeckCarousel',
    component: StackedDeckCarousel,
    parameters: {
        layout: 'padded',
    },
};

export const Default = {
    render: () => {
        const [selectedId, setSelectedId] = useState(null);
        return (
            <div className="max-w-5xl bg-[#F8F9F6] p-6">
                <StackedDeckCarousel
                    title="Meal options"
                    meals={MEALS}
                    deckScopeKey="storybook-default"
                    getKey={(m) => m.id}
                    renderCard={(m, _idx, { isFront, deckLayout }) => (
                        <MealCardClientViewNano
                            deck
                            ribbon={deckLayout === 'ribbon'}
                            deckStackRole={isFront ? 'front' : 'back'}
                            title={m.title}
                            imageUrl={m.imageUrl}
                            imageAlt={m.title}
                            macros={m.macros}
                            selected={selectedId === m.id}
                            imageLoading={isFront ? 'eager' : 'lazy'}
                            onToggleSelected={() => setSelectedId((prev) => (prev === m.id ? null : m.id))}
                            onViewDetails={() => {}}
                        />
                    )}
                />
            </div>
        );
    },
};

