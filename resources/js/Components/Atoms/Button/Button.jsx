/**
 * Smart Kitchen action button — single design-system atom.
 *
 * Variants:
 * - primary: solid brand primary (Landing Get Started, auth, onboarding Continue, saves)
 * - reversed: inverse of primary — white fill, brand border, pressed-green text (AA on white)
 * - secondary: brand-primary wash (`/10` → hover `/20` → active `/30`) — same family as onboarding option buttons
 * - outline: same as ghost (transparent green text) — prefer ghost
 * - ghost: transparent, minimal affordance (Cancel / Close)
 * - tab: same wash family as secondary (unselected day/filter chips; selected chips use primary)
 *
 * @param {{
 *   label?: string;
 *   children?: import('react').ReactNode;
 *   variant?: 'primary' | 'reversed' | 'secondary' | 'outline' | 'ghost' | 'tab';
 *   size?: 'md' | 'sm';
 *   className?: string;
 *   type?: 'button' | 'submit' | 'reset';
 *   disabled?: boolean;
 *   style?: import('react').CSSProperties;
 * }} props
 */
export default function Button({
    label,
    children,
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
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-primary focus-visible:ring-offset-2 focus-visible:ring-offset-white';

    const sizes = {
        md: 'h-[50px] min-h-[50px] px-6 text-[16px] leading-none',
        sm: 'h-[40px] min-h-[40px] px-4 text-[14px] leading-none',
    };

    const secondaryWash = [
        'border border-brand-primary/30 bg-brand-primary/10 text-body',
        'hover:border-brand-primary/50 hover:bg-brand-primary/20 hover:text-body',
        'active:border-brand-primary active:bg-brand-primary/30 active:text-body active:scale-[0.98]',
    ].join(' ');

    const ghost =
        'border border-transparent bg-transparent text-[#5A6B44] hover:bg-[#5A6B44]/10';

    const variants = {
        primary: [
            'border border-transparent bg-brand-primary text-white shadow-sm',
            'hover:bg-brand-primary-hover hover:shadow-md hover:scale-[1.02]',
            'active:bg-brand-primary-pressed active:shadow-inner active:scale-[0.98]',
        ].join(' '),
        reversed: [
            'border border-brand-primary bg-white text-brand-primary-pressed shadow-sm',
            'hover:bg-brand-primary/10 hover:text-brand-primary-pressed hover:shadow-md hover:scale-[1.02]',
            'active:bg-brand-primary/20 active:text-brand-primary-pressed active:scale-[0.98]',
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
            {children ?? label}
        </button>
    );
}
