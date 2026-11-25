import Button from './Button.jsx';

/** Figma: primary button default / hover / pressed (node `2482-2384`). */
const figmaPrimaryButtonFrame =
    'https://www.figma.com/design/YayN7FsAJ6xwrtGUGxna8N/Meal-Craft-App?node-id=2482-2384&t=GM8JRTExU032TFhm-4';

export default {
    // Nest under Button/… so sibling titles (GenderOptionButton, etc.) do not
    // turn this path into a folder-only entry that hides Primary / Ghost / …
    title: 'Design System/02. Atoms/Button/Button',
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
        design: {
            type: 'figma',
            url: figmaPrimaryButtonFrame,
        },
        docs: {
            description: {
                component:
                    'Canonical action button. Import from `Components/Atoms/Button/Button.jsx` (or `Atoms/Button.jsx` re-export). ' +
                    'Variants: primary, reversed (inverse primary — white fill, brand border, pressed-green text; Landing contact CTAs), ' +
                    'secondary (brand-primary `/10`·`/20`·`/30` wash), ghost (transparent; `outline` is the same — use `ghost`), ' +
                    'tab (same wash as secondary; selected tab uses primary). ' +
                    'Tab chips are the shared pattern for day / filter / section switches. ' +
                    'Kitchen logistics `PrimaryButton` is the same primary atom.',
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
            options: ['primary', 'reversed', 'secondary', 'ghost', 'tab'],
        },
        disabled: { control: 'boolean' },
    },
};

export const Primary = {
    args: {
        label: 'Save meal',
        variant: 'primary',
        size: 'sm',
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
        docs: {
            description: {
                story:
                    'Brand-primary wash matching onboarding option buttons: `bg-brand-primary/10`, hover `/20`, active `/30`, `text-body`.',
            },
        },
    },
};

export const Reversed = {
    args: {
        label: 'Chat with AI Agent ›',
        variant: 'reversed',
        size: 'md',
    },
    parameters: {
        docs: {
            description: {
                story:
                    'Inverse of primary: white fill, brand border, `text-brand-primary-pressed` (AA on white). ' +
                    'Landing contact CTAs sit on the brand-blue (`#2F4C9B`) band.',
            },
        },
    },
};

export const Ghost = {
    name: 'Ghost',
    args: {
        label: 'View details',
        variant: 'ghost',
    },
    parameters: {
        docs: {
            description: {
                story:
                    'Transparent green text (Cancel / Close). `variant="outline"` uses the same styles — prefer `ghost`.',
            },
        },
    },
};

export const Tab = {
    args: {
        label: 'Day 1',
        variant: 'tab',
        size: 'sm',
    },
    parameters: {
        docs: {
            description: {
                story:
                    'Unselected chip. Pair with `variant="primary"` for the selected chip. Live: Meal Browse / calorie tiers, ' +
                    'Meal Plan Library days, Crafted For You side choice, customer day-summary tabs, admin profile sections.',
            },
        },
    },
};

export const UsedOnSite = {
    name: 'Used on site',
    render: () => (
        <div className="flex max-w-3xl flex-col gap-6">
            <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    Primary — Landing Get Started, auth, onboarding Continue, save actions
                </p>
                <Button label="Get Started" variant="primary" size="md" />
            </div>
            <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    Reversed — Landing contact CTAs (blue band)
                </p>
                <div className="flex flex-wrap items-center gap-3 rounded-[12px] bg-[#2F4C9B] p-4">
                    <Button label="Chat with AI Agent ›" variant="reversed" size="md" />
                    <Button label="Chat with a Customer Service Agent" variant="reversed" size="md" />
                </div>
            </div>
            <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    Secondary — onboarding wash
                </p>
                <Button label="Learn more" variant="secondary" size="md" />
            </div>
            <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    Ghost — Cancel / Close
                </p>
                <Button label="Cancel" variant="ghost" size="md" />
            </div>
            <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                    Tab — day / filter / section chips (selected = primary, idle = tab)
                </p>
                <div className="flex flex-wrap items-center gap-2">
                    <Button label="Day 1" variant="primary" size="sm" />
                    <Button label="Day 2" variant="tab" size="sm" />
                    <Button label="Day 3" variant="tab" size="sm" />
                </div>
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <Button label="Breakfast" variant="primary" size="sm" />
                    <Button label="Mains" variant="tab" size="sm" />
                    <Button label="500" variant="tab" size="sm" />
                    <Button label="Meals" variant="tab" size="sm" />
                    <Button label="Contact" variant="tab" size="sm" />
                </div>
            </div>
            <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">Disabled</p>
                <Button label="Saving…" variant="primary" size="sm" disabled />
            </div>
        </div>
    ),
};

export const Sizes = {
    name: 'Sizes',
    render: () => (
        <div className="flex flex-wrap items-end gap-4">
            <div className="flex flex-col items-start gap-2">
                <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">md (default)</p>
                <Button label="Continue" variant="primary" size="md" />
            </div>
            <div className="flex flex-col items-start gap-2">
                <p className="text-xs font-semibold uppercase tracking-wide text-neutral-500">sm (admin / consultation)</p>
                <Button label="Save meal" variant="primary" size="sm" />
            </div>
        </div>
    ),
};

export const ModalFooter = {
    name: 'Modal footer',
    render: () => (
        <div className="flex flex-wrap items-center justify-end gap-3 rounded-lg border border-neutral-200 bg-white p-4">
            <Button label="Cancel" variant="ghost" size="sm" />
            <Button label="Save" variant="primary" size="sm" />
        </div>
    ),
    parameters: {
        docs: {
            description: {
                story: 'Dominant product pattern: ghost Cancel + primary Save (modals, sheets, forms).',
            },
        },
    },
};

export const DayTabs = {
    name: 'Day tabs',
    render: () => (
        <div className="flex flex-col gap-4">
            <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">Days</p>
                <div className="flex flex-wrap items-center gap-2" role="tablist" aria-label="Days">
                    {['Day 1', 'Day 2', 'Day 3'].map((label, index) => (
                        <Button key={label} label={label} variant={index === 0 ? 'primary' : 'tab'} size="sm" />
                    ))}
                </div>
            </div>
            <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">Browse / calorie</p>
                <div className="flex flex-wrap items-center gap-2" role="tablist" aria-label="Browse">
                    {['All', 'Breakfast', 'Mains', '500', '600'].map((label, index) => (
                        <Button key={label} label={label} variant={index === 1 ? 'primary' : 'tab'} size="sm" />
                    ))}
                </div>
            </div>
            <div>
                <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">Customer summary</p>
                <div className="flex flex-wrap items-center gap-2" role="tablist" aria-label="Day summary">
                    {['Meals', 'Macros', 'Micros', 'Allergies'].map((label, index) => (
                        <Button key={label} label={label} variant={index === 0 ? 'primary' : 'tab'} size="sm" />
                    ))}
                </div>
            </div>
        </div>
    ),
    parameters: {
        docs: {
            description: {
                story:
                    'Canonical tab pattern: selected = `primary`, idle = `tab`. Used by MealBrowseTabs, MealCalorieTierTabs, ' +
                    'Meal Plan Library / Detail days, Crafted For You side chips, customer Summary / Cook / My Tools, ' +
                    'admin customer profile sections, and AdminPreviewTierPicker.',
            },
        },
    },
};

export const AllVariants = {
    name: 'All variants',
    render: () => (
        <div className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center gap-3">
                <Button label="Primary" variant="primary" size="sm" />
                <Button label="Reversed" variant="reversed" size="sm" />
                <Button label="Secondary" variant="secondary" size="sm" />
                <Button label="Ghost" variant="ghost" size="sm" />
                <Button label="Tab" variant="tab" size="sm" />
                <Button label="Disabled" variant="primary" size="sm" disabled />
            </div>
            <p className="text-xs text-neutral-500">
                Ghost and outline are one look — use <code className="font-mono">variant=&quot;ghost&quot;</code>.
            </p>
        </div>
    ),
};

export const States = {
    name: 'States (all variants)',
    parameters: {
        canvasBackground: 'white',
        layout: 'fullscreen',
    },
    render: () => (
        <div className="flex min-h-screen w-full flex-wrap content-start items-start gap-3 bg-white p-6">
            <Button label="Primary" variant="primary" size="sm" />
            <Button label="Reversed" variant="reversed" size="sm" />
            <Button label="Secondary" variant="secondary" size="sm" />
            <Button label="Ghost" variant="ghost" size="sm" />
            <Button label="Tab" variant="tab" size="sm" />
            <Button label="Disabled" variant="primary" size="sm" disabled />
        </div>
    ),
};

export const Interactions = {
    name: 'Interactions (hover + click)',
    render: () => (
        <div className="flex flex-wrap items-center gap-3 bg-white p-6">
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
