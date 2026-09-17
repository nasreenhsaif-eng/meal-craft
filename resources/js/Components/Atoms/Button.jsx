/**
 * High-fidelity Smart Kitchen button atom.
 *
 * Variants:
 * - primary: solid brand dark green
 * - secondary: Figma hover wash — 50% `#6E8C47` mixed with white (not alpha over the parent)
 * - outline: solid black (not ghost)
 * - ghost: transparent, minimal affordance
 */
export default function Button({
    label,
    variant = 'primary',
    type = 'button',
    size = 'md',
    disabled = false,
    className = '',
    style,
    ...props
}) {
    const base =
        'inline-flex items-center justify-center rounded-[12px] font-montserrat font-bold uppercase tracking-wider ' +
        'transition-all duration-200 ease-in-out ' +
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2 focus-visible:ring-offset-white';

    const sizes = {
        md: 'h-[50px] min-h-[50px] px-6 text-[16px] leading-none',
        sm: 'h-[40px] min-h-[40px] px-4 text-[14px] leading-none',
    };

    const ghost =
        'border border-transparent bg-transparent text-[#5A6B44] hover:bg-[#5A6B44]/10';

    const variants = {
        primary: [
            'border border-transparent bg-[#5A6B44] text-white shadow-sm',
            'hover:bg-[#485636] hover:shadow-md hover:scale-[1.02]',
            'active:bg-[#485636] active:shadow-inner active:scale-[0.98]',
        ].join(' '),
        secondary: [
            'border border-transparent bg-[color-mix(in_srgb,#6E8C47_50%,white)] text-[#364153]',
            'hover:bg-[color-mix(in_srgb,#6E8C47_70%,white)] hover:text-[#364153]',
            'active:border-[#6E8C47] active:bg-[#6E8C47] active:text-white active:scale-[0.98]',
        ].join(' '),
        ghost,
        outline: [
            'border border-[#1F2937] bg-[#1F2937] text-white shadow-sm',
            'hover:border-[#111827] hover:bg-[#111827] hover:shadow-md hover:scale-[1.02]',
            'active:border-black active:bg-black active:shadow-inner active:scale-[0.98]',
        ].join(' '),
    };

    const disabledClass = disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer';

    const sizeClass = sizes[size] ?? sizes.md;
    const variantClass = variants[variant] ?? variants.primary;

    const mergedStyle = style;

    return (
        <button
            type={type}
            disabled={disabled}
            className={`${base} ${sizeClass} ${variantClass} ${disabledClass} ${className}`.trim()}
            style={mergedStyle}
            {...props}
        >
            {label}
        </button>
    );
}

