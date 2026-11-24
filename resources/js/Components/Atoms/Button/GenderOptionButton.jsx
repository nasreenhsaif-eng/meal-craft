import { IconFemale, IconMale } from '../SvgIcons.jsx';
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

/** @param {'male' | 'female'} value */
export function genderOptionIcon(value) {
    return value === 'male' ? <IconMale /> : <IconFemale />;
}
