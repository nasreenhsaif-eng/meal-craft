import { useState } from 'react';
import { MealSlotCarousel } from './ChooseYourMeals.jsx';
import ProtocolMealRow from './ProtocolMealRow.jsx';
import ProtocolMealSlotCard from './ProtocolMealSlotCard.jsx';
import ProtocolMealOptionsScreen from './ProtocolMealOptionsScreen.jsx';
import { mushroomOmeletteAdminMealFixture } from '../mealCardStoryFixtures.js';
import { withConsultationMobileFrame } from '../../Pages/Consultation/consultationStoryDecorators.jsx';

const saladImage =
    'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=800&q=80';

const chickenMeal = {
    id: '1',
    title: 'Rosemary Garlic Chicken w Roasted Vegetables',
    imageUrl: saladImage,
    macros: { calories: 520, protein: 48, carbs: 32, fat: 22 },
};

const liverMeal = {
    id: '5',
    title: 'Seared Beef Liver w Roasted Beetroot',
    imageUrl: saladImage,
    macros: { calories: 480, protein: 42, carbs: 28, fat: 20 },
};

const omeletteMeal = {
    id: 'b1',
    title: mushroomOmeletteAdminMealFixture.title,
    imageUrl: mushroomOmeletteAdminMealFixture.imageUrl,
    macros: mushroomOmeletteAdminMealFixture.macros,
};

const chiaMeal = {
    id: 'b2',
    title: 'Blueberry Walnut Greek Yogurt Chia Pudding',
    imageUrl: saladImage,
    macros: { calories: 280, protein: 18, carbs: 28, fat: 12 },
};

const mainOptions = [
    chickenMeal,
    { ...chickenMeal, id: '2', title: 'Rosemary Chicken Rocca Salad' },
    { ...chickenMeal, id: '3', title: 'Baked Salmon Plate' },
    { ...chickenMeal, id: '4', title: 'Grilled Beef Steak Ratatouille' },
    liverMeal,
    { ...chickenMeal, id: '6', title: 'Vegan Butternut Peanut Stew' },
];

function StoryCanvas({ children, wide = false }) {
    return (
        <div className="w-full px-3 py-4 sm:px-6 sm:py-8">
            <div className={`mx-auto w-full ${wide ? 'sm:max-w-[1100px]' : 'sm:max-w-[28rem]'}`}>{children}</div>
        </div>
    );
}

/**
 * @param {{
 *   dayLabel: string;
 *   sectionTitle: string;
 *   cards: Array<object>;
 *   selectedMeals: Array<object>;
 *   maxSelected: number;
 *   multiSelect?: boolean;
 *   deckScopeKey: string;
 * }} props
 */
function OptionsModalDemo({
    dayLabel,
    sectionTitle,
    cards,
    selectedMeals,
    maxSelected,
    multiSelect = false,
    deckScopeKey,
}) {
    const [open, setOpen] = useState(true);
    const [selectedIds, setSelectedIds] = useState(() => selectedMeals.map((meal) => String(meal.id)));

    const visibleSelected = cards.filter((meal) => selectedIds.includes(String(meal.id)));

    return (
        <StoryCanvas wide={cards.length > 2}>
            <ProtocolMealSlotCard
                title={sectionTitle}
                selectedMeals={visibleSelected}
                multiSelect={multiSelect}
                onSeeOtherOptions={() => setOpen(true)}
                onViewDetails={() => {}}
            />
            {open ? (
                <ProtocolMealOptionsScreen
                    dayLabel={dayLabel}
                    sectionTitle={sectionTitle}
                    onBack={() => setOpen(false)}
                    onConfirm={() => setOpen(false)}
                >
                    <MealSlotCarousel
                        title=""
                        deckOnly
                        cards={cards}
                        selectedIds={selectedIds}
                        maxSelected={maxSelected}
                        onSelect={(meal) => {
                            const id = String(meal.id);
                            setSelectedIds((prev) => {
                                if (maxSelected <= 1) {
                                    return prev.includes(id) ? [] : [id];
                                }

                                if (prev.includes(id)) {
                                    return prev.filter((item) => item !== id);
                                }

                                if (prev.length >= maxSelected) {
                                    return prev;
                                }

                                return [...prev, id];
                            });
                        }}
                        deckScopeKey={deckScopeKey}
                        onViewDetails={() => {}}
                    />
                </ProtocolMealOptionsScreen>
            ) : null}
        </StoryCanvas>
    );
}

export default {
    title: 'Design System/05. Templates & Pages/Consultation/ProtocolSelectedMeals',
    decorators: withConsultationMobileFrame,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Protocol meal slot cards, nano rows, and options screens used inside ChooseYourMeals.',
            },
        },
    },
};

export const NanoMealCard = {
    name: 'Meal card — Nano',
    render: () => (
        <StoryCanvas>
            <div className="mx-auto w-full max-w-[240px]">
                <ProtocolMealRow meal={chickenMeal} selected onViewDetails={() => {}} onSelect={() => {}} />
            </div>
        </StoryCanvas>
    ),
};

export const SlotCardBreakfast = {
    name: 'Slot card — breakfast',
    render: () => (
        <StoryCanvas>
            <ProtocolMealSlotCard
                title="Breakfast"
                selectedMeals={[omeletteMeal]}
                onSeeOtherOptions={() => {}}
                onViewDetails={() => {}}
            />
        </StoryCanvas>
    ),
};

export const SlotCardMains = {
    name: 'Slot card — dual mains',
    render: () => (
        <StoryCanvas wide>
            <ProtocolMealSlotCard
                title="Main Meals"
                selectedMeals={[chickenMeal, liverMeal]}
                multiSelect
                onSeeOtherOptions={() => {}}
                onViewDetails={() => {}}
            />
        </StoryCanvas>
    ),
};

export const OptionsBreakfast = {
    name: 'Options modal — breakfast (2)',
    render: () => (
        <OptionsModalDemo
            dayLabel="Sunday"
            sectionTitle="Breakfast"
            cards={[omeletteMeal, chiaMeal]}
            selectedMeals={[omeletteMeal]}
            maxSelected={1}
            deckScopeKey="story-options-breakfast"
        />
    ),
};

export const OptionsMains = {
    name: 'Options modal — mains (6)',
    render: () => (
        <OptionsModalDemo
            dayLabel="Sunday"
            sectionTitle="Main Meals"
            cards={mainOptions}
            selectedMeals={[chickenMeal, liverMeal]}
            maxSelected={2}
            multiSelect
            deckScopeKey="story-options-mains"
        />
    ),
};
