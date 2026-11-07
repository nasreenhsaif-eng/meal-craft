import MacroGrid from './MacroGrid.jsx';

export default {
    title: 'Design System/03. Molecules/Form and pickers/MacroGrid',
    component: MacroGrid,
    parameters: { layout: 'padded' },
    argTypes: {
        calories: { control: 'text' },
        protein: { control: 'text' },
        carbs: { control: 'text' },
        fat: { control: 'text' },
    },
};

export const Default = {
    args: {
        calories: 520,
        protein: 42,
        carbs: 38,
        fat: 18,
    },
    render: (args) => (
        <div className="flex min-h-[140px] items-center justify-center bg-white p-8">
            <MacroGrid {...args} />
        </div>
    ),
};

