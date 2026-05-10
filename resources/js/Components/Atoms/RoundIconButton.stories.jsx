import RoundIconButton from './RoundIconButton.jsx';
import { IconDelete, IconEdit } from './Icons.jsx';

export default {
    title: 'MealCraft/Atoms/Buttons & Links/RoundIconButton',
    component: RoundIconButton,
    parameters: { layout: 'padded' },
    argTypes: {
        disabled: { control: 'boolean' },
        intent: { control: 'select', options: ['default', 'danger'] },
    },
};

export const Variants = {
    name: 'Variants',
    render: () => (
        <div className="flex items-center gap-3 bg-[#F9FAFB] p-6">
            <RoundIconButton icon={<IconEdit />} label="Edit" intent="default" />
            <RoundIconButton icon={<IconDelete />} label="Delete" intent="danger" />
        </div>
    ),
};

