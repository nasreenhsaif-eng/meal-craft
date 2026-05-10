import React from 'react';

/** Meal Craft control green — matches brand secondary / checkbox fill */
export const SQUARE_CHECKBOX_ACCENT = '#556C37';

/**
 * @param {object} props
 * @param {boolean} [props.checked]
 * @param {() => void} [props.onChange] Ignored when `presentational` is true.
 * @param {string} [props.className]
 * @param {boolean} [props.presentational] If true, no click handler — use inside `<label>` with a native checkbox.
 */
export default function SquareCheckbox({
    checked = false,
    onChange,
    className = '',
    presentational = false,
}) {
    const uncheckedBorder =
        presentational
            ? 'bg-white border-gray-300 group-hover/item:border-[#556C37]'
            : 'bg-white border-gray-300 hover:border-[#556C37]';

    return (
        <div
            onClick={presentational ? undefined : onChange}
            className={`
                relative inline-flex items-center justify-center
                w-5 h-5 min-w-[20px] min-h-[20px]
                rounded-[4px] border-2 transition-all
                ${presentational ? 'pointer-events-none' : 'cursor-pointer'}
                ${checked ? 'bg-[#556C37] border-[#556C37]' : uncheckedBorder}
                ${className}
            `}
        >
            {checked && (
                <svg
                    width="12"
                    height="12"
                    viewBox="0 0 12 12"
                    fill="none"
                    className="text-white"
                    aria-hidden
                >
                    <path
                        d="M2.5 6L4.5 8L9.5 3"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
            )}
        </div>
    );
}
