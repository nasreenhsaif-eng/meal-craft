import { useState } from 'react';
import NavButton from './NavButton.jsx';

/** Labels that match AdminSidebar kitchen engine + intelligence nav. */
const SIDEBAR_LABELS = [
    'Dashboard',
    'Ingredient Library',
    'Meal Library',
    'Meal Tiers Library',
    'Meal Plan Library',
    'Kitchen Production',
    'Subscription Pricing Matrix',
    'Customer Profiles',
    'Discovery Insights',
];

export default {
    title: 'Design System/02. Atoms/Button/NavButton',
    component: NavButton,
    parameters: {
        layout: 'padded',
        docs: {
            description: {
                component:
                    'Admin sidebar row. Used only in AdminSidebar. Built-in icons resolve from the label string.',
            },
        },
    },
    argTypes: {
        isActive: { control: 'boolean' },
        label: {
            control: 'select',
            options: SIDEBAR_LABELS,
        },
    },
};

export const Playground = {
    args: {
        label: 'Ingredient Library',
        isActive: false,
    },
    render: (args) => (
        <div className="w-full max-w-[320px] rounded-xl border border-gray-200 bg-white p-3 font-sans">
            <NavButton {...args} />
        </div>
    ),
};

export const SidebarStack = {
    name: 'Sidebar stack',
    render: () => {
        const [activeId, setActiveId] = useState('Dashboard');

        return (
            <div className="w-full max-w-[320px] rounded-xl border border-gray-200 bg-white p-3 font-sans">
                <div className="space-y-0.5">
                    {SIDEBAR_LABELS.map((label) => (
                        <NavButton
                            key={label}
                            label={label}
                            isActive={activeId === label}
                            onClick={() => setActiveId(label)}
                        />
                    ))}
                </div>
            </div>
        );
    },
};
