/**
 * Pill buttons — brand palette (`#6E8C47`, `#364153`): `primary` (solid border + fill states),
 * `secondary` (soft green wash), `outline` (ghost).
 *
 * @param {{
 *   label: string;
 *   variant?: 'primary' | 'secondary' | 'outline';
 *   size?: 'md' | 'sm';
 *   className?: string;
 *   type?: 'button' | 'submit' | 'reset';
 * }} props
 */
function Button({ label, variant = 'primary', size = 'md', className = '', type = 'button', ...props }) {
    const base =
        'inline-flex items-center justify-center rounded-[12px] font-montserrat font-bold transition-all duration-200 ease-in-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6E8C47] focus-visible:ring-offset-2 focus-visible:ring-offset-mc-cream dark:focus-visible:ring-offset-zinc-900';

    const sizes = {
        md: 'h-[50px] min-h-[50px] px-6 text-[16px] leading-none',
        sm: 'h-[40px] min-h-[40px] px-4 text-[14px] leading-none',
    };

    const variants = {
        primary: [
            'border-2 border-[#5A6B44] bg-[#5A6B44] text-white shadow-sm',
            'hover:bg-[#4F5F3D] hover:border-[#4F5F3D] hover:text-white',
            'active:bg-[#3E4F28] active:border-[#3E4F28] active:text-white',
            'dark:border-2 dark:border-[#5A6B44] dark:bg-[#5A6B44] dark:text-white dark:shadow-sm',
            'dark:hover:bg-[#4F5F3D] dark:hover:border-[#4F5F3D]',
            'dark:active:bg-[#3E4F28] dark:active:border-[#3E4F28]',
        ].join(' '),
        secondary: [
            'border-0 bg-[#6E8C47]/10 text-[#364153]',
            'hover:bg-[#6E8C47]/20',
            'active:scale-95 active:bg-[#6E8C47]/30',
            'dark:border-0 dark:bg-[#6E8C47]/15 dark:text-[#364153]',
            'dark:hover:bg-[#6E8C47]/25',
            'dark:active:bg-[#6E8C47]/35',
        ].join(' '),
        outline: [
            'border-0 bg-transparent shadow-none text-[#364153]',
            'hover:bg-[#F3F4F6] hover:text-[#364153]',
            'active:bg-[#E5E7EB] active:scale-95',
            'dark:border-0 dark:bg-transparent dark:text-zinc-300',
            'dark:hover:bg-zinc-800 dark:hover:text-zinc-100',
            'dark:active:bg-zinc-700',
        ].join(' '),
    };

    let variantClass;
    switch (variant) {
        case 'secondary':
            variantClass = variants.secondary;
            break;
        case 'outline':
            variantClass = variants.outline;
            break;
        case 'primary':
        default:
            variantClass = variants.primary;
            break;
    }

    const sizeClass = sizes[size] ?? sizes.md;

    return (
        <button type={type} className={`${base} ${sizeClass} ${variantClass} ${className}`.trim()} {...props}>
            {label}
        </button>
    );
}

export default Button;
