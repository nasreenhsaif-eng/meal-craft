import React from 'react';

export default function CategoryBadge({ variant = 'meal', label, className = '' }) {
    const LABELS = {
        breakfast: 'Breakfast',
        meal: 'Meal',
        soup: 'Soup',
        sideSalad: 'Side Salad',
        dessert: 'Dessert',
    };

    const displayLabel =
        label ||
        LABELS[variant] ||
        (typeof variant === 'string' && variant.length ? variant.toUpperCase() : '') ||
        'Meal';

    return (
        <div
            className={`inline-flex h-[26px] w-auto items-center justify-center px-[14px] bg-white rounded-full shadow-sm border border-gray-100 ring-1 ring-black/5 ${className}`}
        >
            <span className="font-montserrat text-[11px] font-bold uppercase leading-none tracking-wide text-[#262A22] block whitespace-nowrap">
                {displayLabel}
            </span>
        </div>
    );
}

