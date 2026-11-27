import { IconInfo } from '../../Atoms/SvgIcons.jsx';

/**
 * Bold inline description shown beneath a selected onboarding option.
 *
 * @param {{
 *   children: import('react').ReactNode;
 *   id?: string;
 *   className?: string;
 * }} props
 */
export function OnboardingInlineDescription({ children, id, className = '' }) {
    return (
        <p
            id={id}
            className={[
                'flex items-start gap-1.5 px-1 pt-2 pb-1',
                'font-montserrat text-[13px] font-bold leading-snug text-brand-primary',
                className,
            ].join(' ')}
        >
            <IconInfo className="mt-0.5 shrink-0 opacity-90" />
            <span>{children}</span>
        </p>
    );
}

export default OnboardingInlineDescription;
