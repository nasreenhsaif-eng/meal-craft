import ProtocolMealRow from './ProtocolMealRow.jsx';
import ProtocolMealSlotCard from './ProtocolMealSlotCard.jsx';
import ProtocolMealOptionsScreen from './ProtocolMealOptionsScreen.jsx';
import { mushroomOmeletteAdminMealFixture } from '../mealCardStoryFixtures.js';

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
        <div className="min-h-[80vh] w-full bg-[#F8F9F6] px-4 py-8">
            <div className={`mx-auto ${wide ? 'max-w-2xl' : 'max-w-md'}`}>{children}</div>
        </div>
    );
}

export default {
    title: 'MealCraft/Consultation/ProtocolSelectedMeals',
    parameters: { layout: 'fullscreen' },
};

export const MealRow = {
    name: 'Protocol meal card (old Nano)',
    render: () => (
        <StoryCanvas>
            <div className="mx-auto max-w-[280px]">
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
    name: 'Options screen — breakfast (2)',
    render: () => (
        <StoryCanvas wide>
            <div className="h-[720px] overflow-hidden rounded-[16px] border border-gray-200 shadow-sm">
                <ProtocolMealOptionsScreen
                    dayLabel="Sunday"
                    sectionTitle="Breakfast"
                    options={[omeletteMeal, chiaMeal]}
                    selectedIds={['b1']}
                    maxSelected={1}
                    onToggle={() => {}}
                    onViewDetails={() => {}}
                    onBack={() => {}}
                />
            </div>
        </StoryCanvas>
    ),
};

export const OptionsMains = {
    name: 'Options screen — mains (6)',
    render: () => (
        <StoryCanvas wide>
            <div className="h-[720px] overflow-hidden rounded-[16px] border border-gray-200 shadow-sm">
                <ProtocolMealOptionsScreen
                    dayLabel="Sunday"
                    sectionTitle="Main Meals"
                    options={mainOptions}
                    selectedIds={['1', '5']}
                    maxSelected={2}
                    onToggle={() => {}}
                    onViewDetails={() => {}}
                    onBack={() => {}}
                />
            </div>
        </StoryCanvas>
    ),
};
