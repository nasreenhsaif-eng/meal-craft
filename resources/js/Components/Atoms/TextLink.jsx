/**
 * Standardized text link (shared with Login page).
 * Keep this as the single source of truth for link color + hover/focus states.
 *
 * @param {{
 *   href?: string;
 *   children: import('react').ReactNode;
 *   className?: string;
 *   onClick?: (event: import('react').MouseEvent<HTMLAnchorElement>) => void;
 * }} props
 */
export default function TextLink({ href = '#', children, className = '', onClick, ...props }) {
    const authLinkVisual =
        'text-[#556C37] underline-offset-4 transition-colors hover:text-[#3E4F28] hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2 focus-visible:ring-offset-white';

    return (
        <a
            href={href}
            onClick={onClick}
            className={`font-sans font-semibold ${authLinkVisual} ${className}`.trim()}
            {...props}
        >
            {children}
        </a>
    );
}
