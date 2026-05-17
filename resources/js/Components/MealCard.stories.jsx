import MealCard from './MealCard.jsx';
import MealDetailView from './Molecules/MealDetailView/MealDetailView';
import { mealDetailViewFixture } from './Molecules/MealDetailView/mealDetailViewFixture';
import { mushroomOmeletteAdminMealFixture } from './mealCardStoryFixtures.js';

/**
 * Mirrors the meal library grid shell: white panel on `#F8F9F6`, `p-5`, column width matches {@link MealCard} deck shell (`270px`).
 */
function MealCardStoryCanvas({ children }) {
    return (
        <div className="min-h-[80vh] w-full bg-[#F8F9F6] px-4 py-8 md:px-8">
            <div className="mx-auto max-w-5xl rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm">
                <ul className="m-0 flex list-none justify-center p-0">
                    <li className="flex w-full max-w-[270px] justify-center">{children}</li>
                </ul>
            </div>
        </div>
    );
}

export default {
    title: 'MealCraft/Components/MealCard',
    component: MealCard,
    parameters: {
        layout: 'fullscreen',
    },
    argTypes: {
        isAdmin: { control: 'boolean' },
        selected: { control: 'boolean' },
        category: { control: 'text' },
        meal: { control: 'object' },
    },
};

export const AdminViewWithActions = {
    name: 'Admin view (with actions)',
    args: {
        isAdmin: true,
        adminControls: true,
        showActions: true,
        selected: true,
        meal: mushroomOmeletteAdminMealFixture,
        onViewDetails: () => {},
        onEdit: () => {},
        onToggleSelected: () => {},
    },
    render: (args) => (
        <MealCardStoryCanvas>
            <MealCard {...args} />
        </MealCardStoryCanvas>
    ),
};

export const ClientViewWithActions = {
    name: 'Client view (Craft this Meal + view details)',
    args: {
        isAdmin: false,
        meal: {
            title: 'Turmeric Lentil Soup',
            category: 'Soup',
            imageUrl: 'https://images.unsplash.com/photo-1547592166-23ac45744acd?auto=format&fit=crop&w=1400&q=80',
            macros: { calories: 380, protein: '22g', carbs: '52g', fat: '10g' },
        },
        onViewDetails: () => {},
        onCraftThisMeal: () => {},
    },
    render: (args) => (
        <MealCardStoryCanvas>
            <MealCard {...args} />
        </MealCardStoryCanvas>
    ),
};

export const ImageFallback = {
    name: 'Image fallback (invalid URL → seal)',
    args: {
        isAdmin: true,
        adminControls: true,
        showActions: true,
        selected: false,
        meal: {
            title: 'Post-training recovery shake',
            imageUrl: 'https://example.invalid/does-not-exist.jpg',
            macros: { calories: 385, protein: '36g', carbs: '45g', fat: '8g' },
        },
        onViewDetails: () => {},
        onEdit: () => {},
    },
    render: (args) => (
        <MealCardStoryCanvas>
            <MealCard {...args} />
        </MealCardStoryCanvas>
    ),
};

export const SideBySide = {
    name: 'Side-by-side',
    render: () => (
        <div className="min-h-[80vh] w-full bg-[#F8F9F6] px-4 py-8 md:px-8">
            <div className="mx-auto box-border w-full max-w-5xl rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm md:p-6">
                <ul className="m-0 grid list-none grid-cols-1 justify-items-center gap-6 p-0 sm:grid-cols-2 lg:grid-cols-3">
                    <li className="flex justify-center">
                        <MealCard
                            isAdmin={false}
                            meal={{
                                title: 'Turmeric Lentil Soup',
                                category: 'Soup',
                                imageUrl:
                                    'https://images.unsplash.com/photo-1547592166-23ac45744acd?auto=format&fit=crop&w=1400&q=80',
                                macros: { calories: 380, protein: '22g', carbs: '52g', fat: '10g' },
                            }}
                            onViewDetails={() => {}}
                            onCraftThisMeal={() => {}}
                        />
                    </li>
                    <li className="flex justify-center">
                        <MealCard
                            isAdmin
                            adminControls
                            showActions
                            meal={{
                                title: 'Crispy Cod + Greens',
                                category: 'Meal',
                                imageUrl:
                                    'https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?auto=format&fit=crop&w=1400&q=80',
                                macros: { calories: 540, protein: '46g', carbs: '26g', fat: '22g' },
                            }}
                            onViewDetails={() => {}}
                            onEdit={() => {}}
                        />
                    </li>
                    <li className="flex justify-center">
                        <MealCard
                            isAdmin={false}
                            meal={{
                                title: 'Garden Salad',
                                macros: { calories: 240, protein: '8g', carbs: '28g', fat: '11g' },
                            }}
                            onViewDetails={() => {}}
                            onCraftThisMeal={() => {}}
                        />
                    </li>
                </ul>
            </div>
        </div>
    ),
};

export const ExpandedDetailView = {
    name: 'Expanded detail (MealDetailView)',
    parameters: {
        layout: 'fullscreen',
    },
    render: () => (
        <div className="min-h-screen bg-[#F8F9F6] px-4 py-10 md:px-8">
            <MealDetailView meal={mealDetailViewFixture} />
        </div>
    ),
};
