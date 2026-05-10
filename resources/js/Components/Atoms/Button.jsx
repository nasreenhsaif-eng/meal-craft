/**
 * High-fidelity Smart Kitchen button atom.
 *
 * Variants:
 * - primary: solid brand dark green
 * - secondary: solid brand green (also used as hover target from design)
 * - outline: alias of `ghost` (kept for legacy semantics)
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
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2';

    const sizes = {
        md: 'h-[50px] min-h-[50px] px-6 text-[16px] leading-none',
        sm: 'h-[40px] min-h-[40px] px-4 text-[14px] leading-none',
    };

    const variants = {
        primary: [
            'bg-[#5A6B44] text-white shadow-sm',
            'hover:bg-[#485636] hover:shadow-md hover:scale-[1.02]',
            'active:bg-[#485636] active:shadow-inner active:scale-[0.98]',
        ].join(' '),
        secondary: [
            'bg-[#6E8C47]/50 text-[#2C2C2C]',
            'hover:bg-[#6E8C47]/80 hover:text-white hover:translate-y-[-1px]',
            'active:scale-[0.98]',
        ].join(' '),
        ghost: 'bg-transparent text-[#5A6B44] hover:bg-[#5A6B44]/10',
        outline: 'bg-transparent text-[#5A6B44] hover:bg-[#5A6B44]/10',
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

