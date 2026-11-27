import { useState } from 'react';
import TextInput from './TextInput';
import MicronutrientInput from './MicronutrientInput.jsx';
import Calendar from '../../Molecules/Calendar/Calendar.jsx';

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

/** Fluid frame: fills the viewport up to the desktop field width. */
function ViewportFrame({ children }) {
    return (
        <div className="box-border w-full max-w-full overflow-x-hidden bg-white p-4 dark:bg-zinc-950 sm:p-6">
            <div className="w-full min-w-0 max-w-[492px]">{children}</div>
        </div>
    );
}

export default {
    title: 'Design System/02. Atoms/Input',
    component: TextInput,
    parameters: {
        canvasBackground: 'white',
    },
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
            <ViewportFrame>
                <TextInput {...args} value={value} onChange={(e) => setValue(e.target.value)} />
            </ViewportFrame>
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
            <ViewportFrame>
                <TextInput
                    {...args}
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                    prefixIcon={<SearchIcon />}
                />
            </ViewportFrame>
        );
    },
    args: {
        label: 'Search',
        placeholder: 'Recipes, ingredients…',
        type: 'search',
    },
};

export const Micronutrients = {
    name: 'Micronutrients',
    render: (args) => {
        const [value, setValue] = useState('');

        return (
            <ViewportFrame>
                <MicronutrientInput
                    label="Micronutrients"
                    hint="Enter any nutrients and amounts in one place (one per line or comma-separated)."
                    placeholder={args.placeholder}
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                />
            </ViewportFrame>
        );
    },
    args: {
        placeholder: 'Example: B12: 2.4 mcg • Folate: 400 mcg • Iron: 18 mg • Magnesium: 400 mg',
    },
};

export const Error = {
    render: (args) => {
        const [value, setValue] = useState('');

        return (
            <ViewportFrame>
                <TextInput {...args} value={value} onChange={(e) => setValue(e.target.value)} />
            </ViewportFrame>
        );
    },
    args: {
        label: 'Password',
        placeholder: 'Enter password',
        type: 'password',
        error: 'Password must be at least 8 characters.',
    },
};

export const PasswordReveal = {
    name: 'Password (reveal)',
    render: (args) => {
        const [value, setValue] = useState('');

        return (
            <ViewportFrame>
                <TextInput {...args} value={value} onChange={(e) => setValue(e.target.value)} revealPassword />
            </ViewportFrame>
        );
    },
    args: {
        label: 'Current password',
        placeholder: 'Enter password',
        type: 'password',
    },
};

export const Tel = {
    render: (args) => {
        const [value, setValue] = useState('');

        return (
            <ViewportFrame>
                <TextInput {...args} value={value} onChange={(e) => setValue(e.target.value)} />
            </ViewportFrame>
        );
    },
    args: {
        label: 'Phone',
        placeholder: '(555) 123-4567',
        type: 'tel',
    },
};

function formatLongDate(iso) {
    if (!iso) {
        return '';
    }
    const parts = iso.split('-').map(Number);
    if (parts.length !== 3 || parts.some((n) => Number.isNaN(n))) {
        return iso;
    }
    const [y, m, d] = parts;

    return new Intl.DateTimeFormat(undefined, { month: 'long', day: 'numeric', year: 'numeric' }).format(
        new Date(y, m - 1, d),
    );
}

export const DatePicker = {
    name: 'Date',
    render: () => {
        const [value, setValue] = useState('1990-06-15');

        return (
            <ViewportFrame>
                <TextInput
                    label="Date of birth"
                    type="text"
                    readOnly
                    value={formatLongDate(value)}
                    onChange={() => {}}
                />
                <div className="mt-4">
                    <Calendar
                        mode="single"
                        value={value}
                        onChange={(iso) => setValue(iso ?? '')}
                        defaultMonth={new Date(1990, 5, 1)}
                        aria-label="Date of birth"
                        className="w-full max-w-none"
                    />
                </div>
            </ViewportFrame>
        );
    },
};

export const NumberField = {
    name: 'Number',
    render: (args) => {
        const [value, setValue] = useState('68');

        return (
            <ViewportFrame>
                <TextInput {...args} value={value} onChange={(e) => setValue(e.target.value)} />
            </ViewportFrame>
        );
    },
    args: {
        label: 'Weight (kg)',
        placeholder: '0',
        type: 'number',
    },
};

export const AllViewports = {
    name: 'All variants',
    render: () => {
        const [email, setEmail] = useState('');
        const [search, setSearch] = useState('');
        const [micros, setMicros] = useState('');
        const [password, setPassword] = useState('');
        const [phone, setPhone] = useState('');
        const [weight, setWeight] = useState('68');

        return (
            <div className="box-border w-full max-w-full overflow-x-hidden bg-white p-4 dark:bg-zinc-950 sm:p-6">
                <div className="flex w-full min-w-0 max-w-[492px] flex-col gap-6">
                    <TextInput
                        label="Email"
                        placeholder="you@example.com"
                        type="email"
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                    />
                    <TextInput
                        label="Search"
                        placeholder="Recipes, ingredients…"
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        prefixIcon={<SearchIcon />}
                    />
                    <MicronutrientInput
                        label="Micronutrients"
                        placeholder="B12: 2.4 mcg"
                        value={micros}
                        onChange={(e) => setMicros(e.target.value)}
                    />
                    <TextInput
                        label="Password"
                        placeholder="Enter password"
                        type="password"
                        value=""
                        onChange={() => {}}
                        error="Password must be at least 8 characters."
                    />
                    <TextInput
                        label="Current password"
                        placeholder="Enter password"
                        type="password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        revealPassword
                    />
                    <TextInput
                        label="Phone"
                        placeholder="(555) 123-4567"
                        type="tel"
                        value={phone}
                        onChange={(e) => setPhone(e.target.value)}
                    />
                    <TextInput
                        label="Weight (kg)"
                        placeholder="0"
                        type="number"
                        value={weight}
                        onChange={(e) => setWeight(e.target.value)}
                    />
                </div>
            </div>
        );
    },
};
