import Button from './Button.jsx';

/**
 * Primary action — accessible brand green `#556C37` (WCAG-friendly on white).
 * Thin wrapper around {@link Button} `primary` with admin-token color overrides.
 */
export default function PrimaryButton({ className = '', size, label, type, ...rest }) {
    return (
        <Button
            {...rest}
            type={type}
            size={size}
            label={label}
            variant="primary"
            className={[
                'border-[#556C37] text-[#262A22] shadow-sm',
                'hover:bg-[#556C37]/12 hover:text-[#1a1d16]',
                'active:border-[#556C37] active:bg-[#556C37] active:text-white',
                'focus-visible:ring-[#556C37]',
                'dark:border-[#556C37] dark:text-zinc-100',
                'dark:hover:bg-[#556C37]/15 dark:hover:text-zinc-100',
                'dark:active:bg-[#556C37] dark:active:text-white',
                className,
            ].join(' ')}
        />
    );
}
