import Button from './Atoms/Button.jsx';
import MealCard from './MealCard.jsx';
import SafetyAlerts from './MealSystem/SafetyAlerts.jsx';
import DietaryTags from './MealSystem/DietaryTags.jsx';
import PreferenceTags from './MealSystem/PreferenceTags.jsx';

export default {
    title: 'MealCraft/Components/MealCard',
    component: MealCard,
    parameters: {
        layout: 'padded',
    },
    argTypes: {
        title: { control: 'text' },
        imageUrl: { control: 'text' },
        showAdminSelectionCheckbox: { control: 'boolean' },
    },
};

export const AdminViewWithActions = {
    name: 'Admin view (with actions)',
    args: {
        variant: 'admin',
        title: 'Lemon Chicken Quinoa',
        imageUrl: 'https://images.unsplash.com/photo-1543339308-43e59d6b73a6?auto=format&fit=crop&w=1400&q=80',
        category: 'Meal',
        prepMinutes: 35,
        macros: { calories: 610, protein: '48g', carbs: '44g', fat: '20g' },
        adminControls: true,
        showActions: true,
        selected: true,
        tags: [
            { label: 'High Protein', type: 'dietary' },
            { label: 'Contains Nuts', type: 'dietary' },
            { label: 'Contains Gluten', type: 'dietary' },
            { label: 'Low Carbs', type: 'dietary' },
        ],
        allergyTags: ['Shellfish'],
        dislikeTags: ['No cilantro'],
    },
    render: (args) => (
        <div className="max-w-sm">
            <MealCard {...args} />
        </div>
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
        <div className="max-w-sm">
            <MealCard {...args} />
        </div>
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
        <div className="max-w-sm">
            <MealCard {...args} />
        </div>
    ),
};

export const SideBySide = {
    name: 'Side-by-side',
    render: () => (
        <div className="grid max-w-5xl grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
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
                tags={[{ label: 'Low Carb', type: 'dietary' }, { label: 'High Protein', type: 'dietary' }, { label: 'Contains Dairy', type: 'dietary' }]}
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
    ),
};

