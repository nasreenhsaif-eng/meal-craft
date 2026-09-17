import Button from './Button.jsx';

export default {
    title: 'Design System/02. Atoms/Button/Pill',
    component: Button,
    decorators: [
        (Story) => (
            <div className="min-h-screen w-full bg-white p-6">
                <Story />
            </div>
        ),
    ],
    parameters: {
        canvasBackground: 'white',
        docs: {
            description: {
                component:
                    'Variant strip: primary, secondary, black outline, tab, and disabled. The tab button also lives at Atoms / Button / Tab.',
            },
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
            options: ['primary', 'secondary', 'outline', 'tab'],
        },
        disabled: { control: 'boolean' },
    },
};

export const Primary = {
    args: {
        label: 'Continue',
        variant: 'primary',
        size: 'md',
    },
};

export const Secondary = {
    args: {
        label: 'Learn more',
        variant: 'secondary',
        size: 'md',
    },
};

export const Outline = {
    args: {
        label: 'Skip for now',
        variant: 'outline',
        size: 'md',
    },
};

export const Tab = {
    args: {
        label: 'Day 1',
        variant: 'tab',
        size: 'sm',
    },
};

export const Sizes = {
    name: 'Sizes (md / sm)',
    render: () => (
        <div className="flex flex-wrap items-center gap-3">
            <Button label="Continue" variant="primary" size="md" />
            <Button label="Continue" variant="primary" size="sm" />
            <Button label="Secondary" variant="secondary" size="md" />
            <Button label="Secondary" variant="secondary" size="sm" />
        </div>
    ),
};

export const AllVariants = {
    name: 'All variants',
    render: () => (
        <div className="flex flex-wrap items-center gap-3">
            <Button label="Primary" variant="primary" size="sm" />
            <Button label="Secondary" variant="secondary" size="sm" />
            <Button label="Outline" variant="outline" size="sm" />
            <Button label="Tab" variant="tab" size="sm" />
            <Button label="Disabled" variant="primary" size="sm" disabled />
        </div>
    ),
};
