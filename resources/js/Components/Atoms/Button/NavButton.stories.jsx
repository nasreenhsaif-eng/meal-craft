import NavButton from './NavButton.jsx';
import { useState } from 'react';

function IconDatabase({ className }) {
    return (
        <svg className={className} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <ellipse cx="12" cy="5" rx="9" ry="3" stroke="currentColor" strokeWidth="2" />
            <path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5" stroke="currentColor" strokeWidth="2" />
            <path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3" stroke="currentColor" strokeWidth="2" />
        </svg>
    );
}

function IconMealHub({ className }) {
    return (
        <svg className={className} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h2l1 5M15 15h4"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function IconCalendar({ className }) {
    return (
        <svg className={className} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" strokeWidth="2" />
            <path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

function IconUsers({ className }) {
    return (
        <svg className={className} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function IconChart({ className }) {
    return (
        <svg className={className} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path d="M3 3v18h18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
            <path
                d="M7 16l4-6 3 3 5-8"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

export default {
    title: 'MealCraft/Atoms/Buttons & Links/NavButton',
    component: NavButton,
    parameters: { layout: 'padded' },
    argTypes: {
        isActive: { control: 'boolean' },
        label: {
            control: 'select',
            options: ['Dashboard', 'Ingredient DB', 'Meal Hub', 'Meal Plans', 'Customer Profiles', 'Discovery Insights'],
        },
    },
};

export const Playground = {
    args: {
        label: 'Ingredient DB',
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
        const items = ['Dashboard', 'Ingredient DB', 'Meal Hub', 'Meal Plans', 'Customer Profiles', 'Discovery Insights'];

        return (
            <div className="w-full max-w-[320px] rounded-xl border border-gray-200 bg-white p-3 font-sans">
                <div className="space-y-0.5">
                    {items.map((label) => (
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

