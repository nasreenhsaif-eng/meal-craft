import Button from './Atoms/Button.jsx';
import MealCard from './MealCard.jsx';
import SafetyAlerts from './MealSystem/SafetyAlerts.jsx';
import DietaryTags from './MealSystem/DietaryTags.jsx';
import PreferenceTags from './MealSystem/PreferenceTags.jsx';
import MealDetailView from './Molecules/MealDetailView/MealDetailView';
import { mealDetailViewFixture } from './Molecules/MealDetailView/mealDetailViewFixture';
import { adminMealCardWithActionsFixture } from './mealCardStoryFixtures.js';

/**
 * Storybook canvas: give the card a definite block size so `h-full` / flex chains cannot
 * resolve to zero height (global preview uses a fixed full-viewport shell).
 */
function MealCardStoryCanvas({ children }) {
    return (
        <div className="flex min-h-[80vh] w-full justify-center px-4 py-8 md:px-8 md:py-12">
            <div className="flex h-[min(680px,85vh)] w-full max-w-sm shrink-0 flex-col overflow-y-auto rounded-[12px] bg-[#F1F3EF] p-6 shadow-inner ring-1 ring-[#5A6B44]/15 md:p-8">
                <div className="flex min-h-0 flex-1 flex-col">{children}</div>
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
        title: { control: 'text' },
        imageUrl: { control: 'text' },
        showAdminSelectionCheckbox: { control: 'boolean' },
        isLoading: { control: 'boolean' },
        meal: { control: false },
    },
};

export const AdminViewWithActions = {
    name: 'Admin view (with actions)',
    render: () => (
        <MealCardStoryCanvas>
            <MealCard
                variant="admin"
                meal={adminMealCardWithActionsFixture}
                adminControls
                showActions
                selected
            />
        </MealCardStoryCanvas>
    ),
};

export const EmptyState = {
    name: 'Empty state (no title / meal)',
    args: {
        variant: 'admin',
        title: '',
        adminControls: true,
        showActions: true,
    },
    render: (args) => (
        <MealCardStoryCanvas>
            <MealCard {...args} />
        </MealCardStoryCanvas>
    ),
};

export const LoadingState = {
    name: 'Loading state',
    args: {
        isLoading: true,
    },
    render: (args) => (
        <MealCardStoryCanvas>
            <MealCard {...args} />
        </MealCardStoryCanvas>
    ),
};

export const AdminGridNoSelectionCheckbox = {
    name: 'Admin grid (no selection checkbox)',
    args: {
        variant: 'admin',
        title: 'Herb-roasted vegetables',
        imageUrl: 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=1400&q=80',
        category: 'Side Salad',
        prepMinutes: 25,
        macros: { calories: 180, protein: '4g', carbs: '22g', fat: '9g' },
        adminControls: true,
        showAdminSelectionCheckbox: false,
        showActions: true,
        tags: [{ label: 'Vegan', type: 'dietary' }],
    },
    render: (args) => (
        <MealCardStoryCanvas>
            <MealCard {...args} />
        </MealCardStoryCanvas>
    ),
};

export const ImageFallback = {
    name: 'Image fallback (broken image)',
    args: {
        variant: 'admin',
        title: 'Post-training recovery shake',
        imageUrl: 'https://example.invalid/does-not-exist.jpg',
        category: 'Meal',
        prepMinutes: 8,
        macros: { calories: 385, protein: '36g', carbs: '45g', fat: '8g' },
        adminControls: true,
        showActions: true,
        selected: false,
        tags: [{ label: 'High Protein', type: 'dietary' }],
        nutrientHighlights: ['Zinc', 'Magnesium'],
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
        <div className="flex min-h-[80vh] w-full justify-center px-4 py-8 md:px-8">
            <div className="box-border w-full max-w-5xl rounded-[12px] bg-[#F9FAFB] p-6 ring-1 ring-gray-200/60 md:p-8">
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <MealCard
                        variant="client"
                        title="Turmeric Lentil Soup"
                        imageUrl="https://images.unsplash.com/photo-1547592166-23ac45744acd?auto=format&fit=crop&w=1400&q=80"
                        category="Soup"
                        prepMinutes={20}
                        macros={{ calories: 380, protein: '22g', carbs: '52g', fat: '10g' }}
                        tags={[{ label: 'Anti-Inflammatory', type: 'dietary' }, { label: 'Vegan', type: 'dietary' }]}
                        actionSlot={<Button label="View details" variant="primary" type="button" className="w-full justify-center" />}
                    />
                    <MealCard
                        variant="admin"
                        title="Crispy Cod + Greens"
                        imageUrl="https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?auto=format&fit=crop&w=1400&q=80"
                        category="Meal"
                        prepMinutes={30}
                        macros={{ calories: 540, protein: '46g', carbs: '26g', fat: '22g' }}
                        tags={[
                            { label: 'Low Carb', type: 'dietary' },
                            { label: 'High Protein', type: 'dietary' },
                            { label: 'Contains Dairy', type: 'dietary' },
                        ]}
                        safetySlot={
                            <div className="space-y-2">
                                <SafetyAlerts alerts={[{ label: 'Dairy', variant: 'allergy' }, { label: 'G6PD', variant: 'g6pd' }]} />
                                <DietaryTags tags={['High Protein', 'Low Carbs']} />
                                <PreferenceTags tags={['Without onions']} />
                            </div>
                        }
                    />
                    <MealCard
                        variant="client"
                        title="Garden Salad"
                        category="Side Salad"
                        prepMinutes={10}
                        macros={{ calories: 240, protein: '8g', carbs: '28g', fat: '11g' }}
                        tags={[{ label: 'Vegan', type: 'dietary' }, { label: 'Gluten Free', type: 'dietary' }]}
                    />
                </div>
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
