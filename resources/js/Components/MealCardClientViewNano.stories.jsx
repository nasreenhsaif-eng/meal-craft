import MealCardClientViewNano from './MealCardClientViewNano.jsx';

const macros = { calories: 520, protein: '44g', carbs: '38g', fat: '22g' };

export default {
    title: 'MealCraft/Components/MealCardClientViewNano',
    component: MealCardClientViewNano,
    parameters: {
        layout: 'padded',
    },
};

/** Same shell as consultation mobile / stack deck (macro row between title and VIEW DETAILS). */
export const DeckWithMacros = {
    name: 'Deck (consultation card)',
    render: () => (
        <div className="mx-auto w-full max-w-[320px] bg-[#F8F9F6] p-4">
            <MealCardClientViewNano
                deck
                title="Post-workout salmon bowl"
                imageUrl="https://images.unsplash.com/photo-1553621042-f6e147245754?auto=format&fit=crop&w=1400&q=80"
                imageAlt="Salmon bowl"
                macros={macros}
                onToggleSelected={() => {}}
                onViewDetails={() => {}}
            />
        </div>
    ),
};

export const StandalonePreview = {
    name: 'Standalone (no deck)',
    render: () => (
        <div className="inline-block p-4">
            <MealCardClientViewNano
                title="Egg white veggie scramble"
                imageUrl="https://images.unsplash.com/photo-1525351484163-7529414344d8?auto=format&fit=crop&w=1400&q=80"
                macros={macros}
                onToggleSelected={() => {}}
                onViewDetails={() => {}}
            />
        </div>
    ),
};
