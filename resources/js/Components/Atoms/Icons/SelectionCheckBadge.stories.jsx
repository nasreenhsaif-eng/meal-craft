import SelectionCheckBadge from './SelectionCheckBadge.jsx';

export default {
    title: 'Design System/02. Atoms/Badge/SelectionCheckBadge',
    component: SelectionCheckBadge,
    parameters: {
        layout: 'padded',
        canvasBackground: 'white',
    },
    argTypes: {
        size: {
            control: 'select',
            options: ['sm', 'md', 'lg'],
        },
    },
};

export const Sizes = {
    name: 'Sizes (sm / md / lg)',
    render: () => (
        <div className="flex items-center gap-6 bg-[#F8F9F6] p-8">
            <SelectionCheckBadge size="sm" />
            <SelectionCheckBadge size="md" />
            <SelectionCheckBadge size="lg" />
        </div>
    ),
};
