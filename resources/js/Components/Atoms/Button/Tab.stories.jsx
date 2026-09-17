import Button from './Button.jsx';

export default {
    title: 'Design System/02. Atoms/Button/Tab',
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
                component: 'White bordered tab button. Used for day and filter tabs, not page navigation.',
            },
        },
    },
    argTypes: {
        label: { control: 'text' },
        size: {
            control: 'select',
            options: ['md', 'sm'],
        },
    },
};

export const Default = {
    args: {
        label: 'Tab',
        variant: 'tab',
        size: 'sm',
    },
};

export const DayTabs = {
    name: 'Day tabs',
    render: () => (
        <div className="flex flex-wrap items-center gap-2">
            {['Day 1', 'Day 2', 'Day 3'].map((label, index) => (
                <Button key={label} label={label} variant={index === 0 ? 'primary' : 'tab'} size="sm" />
            ))}
        </div>
    ),
};
