import SafetyAlerts from '../MealSystem/SafetyAlerts.jsx';

export default {
    title: 'MealCraft/Atoms/Meal System/SafetyAlerts',
    component: SafetyAlerts,
    parameters: { layout: 'padded' },
};

export const CommonAllergies = {
    name: 'Common allergies + G6PD',
    render: () => (
        <div className="max-w-2xl bg-white p-8">
            <SafetyAlerts
                alerts={[
                    { label: 'Sesame', variant: 'allergy' },
                    { label: 'Wheat', variant: 'allergy' },
                    { label: 'Soy', variant: 'allergy' },
                    { label: 'Shellfish', variant: 'allergy' },
                    { label: 'Eggs', variant: 'allergy' },
                    { label: 'Milk', variant: 'allergy' },
                    { label: 'Fish', variant: 'allergy' },
                    { label: 'Tree nuts', variant: 'allergy' },
                    { label: 'Beans', variant: 'allergy' },
                    { label: 'Peanuts', variant: 'allergy' },
                    { label: 'G6PD', variant: 'g6pd' },
                ]}
            />
        </div>
    ),
};

