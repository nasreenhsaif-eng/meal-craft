import { useState } from 'react';
import DropdownTextInput from './DropdownTextInput.jsx';
import MultiPillDropdown from './MultiPillDropdown.jsx';

function ViewportFrame({ children }) {
    return (
        <div className="box-border w-full max-w-full overflow-x-hidden bg-white p-4 dark:bg-zinc-950 sm:p-6">
            <div className="w-full min-w-0 max-w-[492px]">{children}</div>
        </div>
    );
}

export default {
    title: 'Design System/03. Molecules/Dropdown',
    parameters: {
        canvasBackground: 'white',
    },
};

export const Category = {
    render: (args) => {
        const [value, setValue] = useState('');

        return (
            <ViewportFrame>
                <DropdownTextInput
                    label={args.label}
                    value={value}
                    options={['Meal', 'Snack', 'Soup', 'Side Salad']}
                    onChange={setValue}
                />
            </ViewportFrame>
        );
    },
    args: {
        label: 'Category',
    },
};

export const AccessRights = {
    name: 'Access rights',
    render: (args) => {
        const [value, setValue] = useState('View');

        return (
            <ViewportFrame>
                <DropdownTextInput
                    label={args.label}
                    value={value}
                    options={['View', 'View and Edit']}
                    onChange={setValue}
                />
            </ViewportFrame>
        );
    },
    args: {
        label: 'Access Rights',
    },
};

export const MultiPill = {
    name: 'Multi-pill dropdown',
    render: (args) => {
        const [selected, setSelected] = useState(['vegan', 'gluten-free']);

        return (
            <ViewportFrame>
                <MultiPillDropdown
                    label={args.label}
                    options={[
                        { value: 'vegan', label: 'Vegan' },
                        { value: 'vegetarian', label: 'Vegetarian' },
                        { value: 'gluten-free', label: 'Gluten free' },
                        { value: 'dairy-free', label: 'Dairy free' },
                    ]}
                    selectedValues={selected}
                    onChange={setSelected}
                />
            </ViewportFrame>
        );
    },
    args: {
        label: 'Dietary tags',
    },
};
