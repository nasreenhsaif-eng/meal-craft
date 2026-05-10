import { useState } from 'react';
import TextInput from './TextInput';
import DropdownTextInput from './DropdownTextInput.jsx';
import MicronutrientInput from './MicronutrientInput.jsx';

/**
 * Magnifying glass — outline path aligned with common Figma / Heroicons exports, 24×24 viewBox.
 */
function SearchIcon() {
    return (
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"
            />
        </svg>
    );
}

export default {
    title: 'MealCraft/Atoms/TextInput',
    component: TextInput,
    argTypes: {
        label: { control: 'text' },
        placeholder: { control: 'text' },
        type: { control: 'text' },
        error: { control: 'text' },
    },
};

export const Default = {
    render: (args) => {
        const [value, setValue] = useState('');

        return (
            <div className="bg-white p-6 dark:bg-zinc-950">
                <div style={{ width: '492px' }}>
                    <TextInput
                        {...args}
                        value={value}
                        onChange={(e) => setValue(e.target.value)}
                    />
                </div>
            </div>
        );
    },
    args: {
        label: 'Email',
        placeholder: 'you@example.com',
        type: 'email',
    },
};

export const Search = {
    render: (args) => {
        const [value, setValue] = useState('');

        return (
            <div className="bg-white p-6 dark:bg-zinc-950">
                <div style={{ width: '492px' }}>
                    <TextInput
                        {...args}
                        value={value}
                        onChange={(e) => setValue(e.target.value)}
                        prefixIcon={<SearchIcon />}
                    />
                </div>
            </div>
        );
    },
    args: {
        label: 'Search',
        placeholder: 'Recipes, ingredients…',
        type: 'search',
    },
};

export const Dropdown = {
    render: (args) => {
        const [value, setValue] = useState('');

        return (
            <div className="bg-white p-6 dark:bg-zinc-950">
                <div style={{ width: '492px' }}>
                    <DropdownTextInput
                        label={args.label}
                        value={value}
                        options={['Meal', 'Snack', 'Soup', 'Side Salad']}
                        onChange={setValue}
                    />
                </div>
            </div>
        );
    },
    args: {
        label: 'Category',
    },
};

export const Micronutrients = {
    name: 'Micronutrients',
    render: (args) => {
        const [value, setValue] = useState('');

        return (
            <div className="bg-white p-6 dark:bg-zinc-950">
                <div style={{ width: '492px' }}>
                    <MicronutrientInput
                        label="Micronutrients"
                        hint="Enter any nutrients and amounts in one place (one per line or comma-separated)."
                        placeholder={args.placeholder}
                        value={value}
                        onChange={(e) => setValue(e.target.value)}
                    />
                </div>
            </div>
        );
    },
    args: {
        placeholder: 'Example: B12: 2.4 mcg • Folate: 400 mcg • Iron: 18 mg • Magnesium: 400 mg',
    },
};

export const DropdownOnly = {
    render: (args) => {
        const [value, setValue] = useState('View');

        return (
            <div className="bg-white p-6 dark:bg-zinc-950">
                <div style={{ width: '492px' }}>
                    <DropdownTextInput
                        label={args.label}
                        value={value}
                        options={['View', 'View and Edit']}
                        onChange={setValue}
                        className="!max-w-none"
                    />
                </div>
            </div>
        );
    },
    args: {
        label: 'Access Rights',
    },
};

export const Error = {
    render: (args) => {
        const [value, setValue] = useState('');

        return (
            <div className="bg-white p-6 dark:bg-zinc-950">
                <div style={{ width: '492px' }}>
                    <TextInput
                        {...args}
                        value={value}
                        onChange={(e) => setValue(e.target.value)}
                    />
                </div>
            </div>
        );
    },
    args: {
        label: 'Password',
        placeholder: 'Enter password',
        type: 'password',
        error: 'Password must be at least 8 characters.',
    },
};
