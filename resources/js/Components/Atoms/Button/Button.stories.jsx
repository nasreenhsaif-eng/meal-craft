import Button from '../Button.jsx';

/** Figma: primary button default / hover / pressed (node `2482-2384`). */
const figmaPrimaryButtonFrame =
    'https://www.figma.com/design/YayN7FsAJ6xwrtGUGxna8N/Meal-Craft-App?node-id=2482-2384&t=GM8JRTExU032TFhm-4';

/** Copy deck: common actions — `secondary` is the soft brand wash (merged from former SecondaryButton). */
const buttonLabelSpecs = [
    { label: 'Save meal', variant: 'primary' },
    { label: 'Choose file', variant: 'ghost' },
    { label: 'View details', variant: 'ghost' },
    { label: '+ Add ingredient', variant: 'primary' },
    { label: 'Log in', variant: 'primary' },
    { label: 'Balanced', variant: 'secondary' },
    { label: 'Sickle Cell', variant: 'ghost' },
    { label: 'Cycle Sync', variant: 'secondary' },
];

export default {
    title: 'MealCraft/Atoms/Buttons & Links/Buttons',
    component: Button,
    parameters: {
        design: {
            type: 'figma',
            url: figmaPrimaryButtonFrame,
        },
    },
    argTypes: {
        label: { control: 'text' },
        className: { control: 'text' },
        type: { control: 'text' },
        size: {
            control: 'select',
            options: ['md', 'sm'],
        },
        variant: {
            control: 'select',
            options: ['primary', 'secondary', 'outline', 'ghost'],
        },
        disabled: { control: 'boolean' },
    },
};

export const Primary = {
    args: {
        label: 'Save meal',
        variant: 'primary',
        size: "sm",
        className: "",
        type: "button"
    },
    parameters: {
        design: {
            type: 'figma',
            url: figmaPrimaryButtonFrame,
        },
    },
};

export const Secondary = {
    args: {
        label: 'Learn more',
        variant: 'secondary',
    },
    parameters: {
        design: {
            type: 'figma',
            url: figmaPrimaryButtonFrame,
        },
    },
};

export const Outline = {
    args: {
        label: 'View details',
        variant: 'outline',
    },
    parameters: {
        design: {
            type: 'figma',
            url: figmaPrimaryButtonFrame,
        },
    },
};

/** Former SecondaryButton row — soft wash variant. */
export const SecondaryRow = {
    name: 'Secondary row',
    render: () => (
        <div className="flex flex-wrap items-center gap-3 bg-white p-6 dark:bg-zinc-900">
            <Button label="Nutrition tips" variant="secondary" />
            <Button label="View guidelines" variant="secondary" />
            <Button label="Cycle sync" variant="secondary" />
        </div>
    ),
};

/** Same `md` / `sm` sizing — primary vs outline alignment. */
export const OutlineWithPrimary = {
    name: 'Outline with primary',
    render: () => (
        <div className="flex flex-wrap items-center gap-3 bg-white p-4 dark:bg-zinc-900">
            <Button label="Save meal" variant="primary" />
            <Button label="View details" variant="outline" />
        </div>
    ),
};

export const ButtonLabels = {
    render: () => (
        <div className="flex max-w-2xl flex-wrap gap-3">
            {buttonLabelSpecs.map(({ label, variant }) => (
                <Button key={label} label={label} variant={variant} />
            ))}
        </div>
    ),
};

export const SecondaryShortLabel = {
    name: 'Secondary (short label)',
    args: {
        label: 'OK',
        variant: 'secondary',
    },
};

export const States = {
    name: 'States (primary / secondary / ghost)',
    render: () => (
        <div className="flex flex-wrap items-center gap-3 bg-white p-6 dark:bg-zinc-900">
            <Button label="Primary" variant="primary" size="sm" />
            <Button label="Secondary" variant="secondary" size="sm" />
            <Button label="Ghost" variant="ghost" size="sm" />
            <Button label="Disabled" variant="primary" size="sm" disabled />
        </div>
    ),
};

export const Interactions = {
    name: 'Interactions (hover + click)',
    render: () => (
        <div className="flex flex-wrap items-center gap-3 bg-white p-6 dark:bg-zinc-900">
            <Button label="Hover & click me" variant="primary" size="sm" />
        </div>
    ),
    play: async ({ canvasElement }) => {
        const button = canvasElement.querySelector('button');
        if (!button) {
            return;
        }

        button.dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));
        button.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
        button.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
        button.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
        button.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        button.dispatchEvent(new MouseEvent('mouseout', { bubbles: true }));
        button.dispatchEvent(new MouseEvent('mouseleave', { bubbles: true }));
    },
};
