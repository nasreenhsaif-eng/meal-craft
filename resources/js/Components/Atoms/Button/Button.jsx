/**
 * Smart Kitchen action button — single design-system atom.
 *
 * Variants:
 * - primary: solid brand dark green
 * - secondary: Figma hover wash — 50% `#6E8C47` mixed with white
 * - outline: same as ghost (transparent green text)
 * - ghost: transparent, minimal affordance
 * - tab: same wash family as secondary (day/filter chips; selected chips use primary)
 *
 * @param {{
 *   label: string;
 *   variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'tab';
 *   size?: 'md' | 'sm';
 *   className?: string;
 *   type?: 'button' | 'submit' | 'reset';
 *   disabled?: boolean;
 *   style?: import('react').CSSProperties;
 * }} props
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

    const secondaryWash = [
        'border border-transparent bg-[color-mix(in_srgb,#6E8C47_50%,white)] text-[#364153]',
        'hover:bg-[color-mix(in_srgb,#6E8C47_70%,white)] hover:text-[#364153]',
        'active:border-[#6E8C47] active:bg-[#6E8C47] active:text-white active:scale-[0.98]',
    ].join(' ');

    const ghost =
        'border border-transparent bg-transparent text-[#5A6B44] hover:bg-[#5A6B44]/10';

    const variants = {
        primary: [
            'border border-transparent bg-[#5A6B44] text-white shadow-sm',
            'hover:bg-[#485636] hover:shadow-md hover:scale-[1.02]',
            'active:bg-[#485636] active:shadow-inner active:scale-[0.98]',
        ].join(' '),
        secondary: secondaryWash,
        tab: secondaryWash,
        ghost,
        outline: ghost,
    };

    const disabledClass = disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer';
    const sizeClass = sizes[size] ?? sizes.md;
    const variantClass = variants[variant] ?? variants.primary;

    return (
        <button
            type={type}
            disabled={disabled}
            className={`${base} ${sizeClass} ${variantClass} ${disabledClass} ${className}`.trim()}
            style={style}
            {...props}
        >
            {label}
        </button>
    );
}
