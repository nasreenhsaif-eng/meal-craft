/**
 * Admin sidebar nav row — icon + label with active poka‑yoke affordances.
 *
 * @param {{
 *   label: string;
 *   icon?: import('react').ReactNode;
 *   isActive?: boolean;
 *   href?: string;
 *   onClick?: (event: import('react').MouseEvent<HTMLButtonElement | HTMLAnchorElement>) => void;
 *   className?: string;
 *   type?: 'button' | 'submit' | 'reset';
 * }} props
 */
export default function NavButton({ icon, label, isActive = false, href, onClick, className = '', type = 'button' }) {
    const active = Boolean(isActive);
    const tint = 'bg-[#6E8C47]/5';
    const textActive = 'text-[#5A6B44]';
    const textHover = 'hover:text-[#5A6B44]';

    const IconDatabase = ({ className: svgClassName }) => (
        <svg className={svgClassName} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <ellipse cx="12" cy="5" rx="9" ry="3" stroke="currentColor" strokeWidth="2" />
            <path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5" stroke="currentColor" strokeWidth="2" />
            <path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3" stroke="currentColor" strokeWidth="2" />
        </svg>
    );

    const IconMealHub = ({ className: svgClassName }) => (
        <svg className={svgClassName} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2M7 2v20M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h2l1 5M15 15h4"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );

    const IconDashboard = ({ className: svgClassName }) => (
        <svg className={svgClassName} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M4 11.5L12 4l8 7.5V20a2 2 0 0 1-2 2h-4v-6H10v6H6a2 2 0 0 1-2-2v-8.5Z"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinejoin="round"
            />
        </svg>
    );

    const IconCalendar = ({ className: svgClassName }) => (
        <svg className={svgClassName} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" strokeWidth="2" />
            <path d="M16 2v4M8 2v4M3 10h18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );

    const IconUsers = ({ className: svgClassName }) => (
        <svg className={svgClassName} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );

    const IconChart = ({ className: svgClassName }) => (
        <svg className={svgClassName} width={20} height={20} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path d="M3 3v18h18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
            <path d="M7 16l4-6 3 3 5-8" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );

    const iconForLabel = () => {
        const key = String(label ?? '').trim();
        if (key === 'Dashboard') {
            return <IconDashboard />;
        }
        if (key === 'Ingredient DB') {
            return <IconDatabase />;
        }
        if (key === 'Meal Hub') {
            return <IconMealHub />;
        }
        if (key === 'Meal Plans') {
            return <IconCalendar />;
        }
        if (key === 'Customer Profiles') {
            return <IconUsers />;
        }
        if (key === 'Discovery Insights') {
            return <IconChart />;
        }
        return null;
    };

    const resolvedIcon = icon ?? iconForLabel();

    const handleClick = (event) => {
        if (href) {
            event.preventDefault();
        }
        onClick?.(event);
    };

    const commonClassName = `relative flex items-center gap-3 rounded-md py-2.5 pl-3 pr-3 font-sans text-sm font-semibold no-underline outline-none transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 ${
        active ? `${tint} ${textActive}` : `text-[#262A22] hover:bg-[#6E8C47]/8 ${textHover}`
    } ${className}`.trim();

    const contents = (
        <>
            {isActive && (
                <div
                    className="pointer-events-none absolute left-0 top-0 h-full w-1 rounded-r-sm bg-[#6E8C47]"
                    aria-hidden
                />
            )}
            <span className="pointer-events-none shrink-0 text-current">{resolvedIcon}</span>
            <span className="relative z-10 min-w-0 truncate">{label}</span>
        </>
    );

    if (href) {
        return (
            <a href={href} onClick={handleClick} aria-current={active ? 'page' : undefined} className={commonClassName}>
                {contents}
            </a>
        );
    }

    return (
        <button type={type} onClick={handleClick} aria-current={active ? 'page' : undefined} className={commonClassName}>
            {contents}
        </button>
    );
}

