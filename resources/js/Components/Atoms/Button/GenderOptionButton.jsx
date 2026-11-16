import { OnboardingOptionButton } from './OnboardingOptionButton.jsx';

/**
 * Male / female choice — same pale-green selection button as diet protocol.
 * Icon + label are centered as a pair.
 *
 * @param {{
 *   label: string;
 *   selected?: boolean;
 *   disabled?: boolean;
 *   busy?: boolean;
 *   onSelect?: () => void;
 *   icon?: import('react').ReactNode;
 *   className?: string;
 * }} props
 */
export function GenderOptionButton({
    label,
    selected = false,
    disabled = false,
    busy = false,
    onSelect,
    icon,
    className = '',
}) {
    return (
        <OnboardingOptionButton
            label={label}
            selected={selected}
            disabled={disabled || busy}
            busy={busy}
            onSelect={onSelect}
            icon={icon}
            align="center"
            className={className}
        />
    );
}

function IconMale() {
    return (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden>
            <circle cx="10" cy="14" r="4" stroke="currentColor" strokeWidth="1.75" />
            <path d="M14 10l6-6M16 4h4v4" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
        </svg>
    );
}

function IconFemale() {
    return (
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden>
            <circle cx="12" cy="9" r="4" stroke="currentColor" strokeWidth="1.75" />
            <path d="M12 13v7M9 17h6" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" />
        </svg>
    );
}

/** @param {'male' | 'female'} value */
export function genderOptionIcon(value) {
    return value === 'male' ? <IconMale /> : <IconFemale />;
}
