import MealCraftLogo from './Atoms/Logo/MealCraftLogo.jsx';

/**
 * Login / header brand mark (sprite seal or horizontal lockup).
 * For arbitrary sprite slices, use `Atoms/Logo/MealCraftLogo.jsx` directly.
 *
 * @param {{
 *   variant?: string;
 *   size?: number | string;
 *   sizePreset?: 'header' | 'login';
 *   color?: Record<string, string>;
 *   brandName?: string;
 *   className?: string;
 *   'aria-label'?: string;
 *   animatedLoop?: boolean;
 * }} props
 */
export default function Logo({
    variant = 'horizontal',
    size,
    sizePreset,
    color: _paletteOverride,
    brandName = 'Meal Craft',
    className = '',
    'aria-label': ariaLabel = `${brandName} logo`,
    animatedLoop: _animatedLoop = true,
}) {
    const raw =
        variant === undefined || variant === null || String(variant).trim() === ''
            ? 'horizontal'
            : String(variant).trim();

    if (raw === 'animated') {
        let px = 48;
        if (typeof size === 'number' && Number.isFinite(size) && size > 0) {
            px = size;
        } else if (sizePreset === 'login') {
            px = 56;
        } else if (sizePreset === 'header') {
            px = 32;
        }

        return (
            <div className={`flex w-full min-w-0 max-w-full items-center justify-center ${className}`.trim()}>
                <MealCraftLogo variant="seal-md" width={px} alt={ariaLabel} />
            </div>
        );
    }

    const lockupWidth =
        typeof size === 'number' && Number.isFinite(size) && size > 0
            ? size
            : sizePreset === 'header'
              ? 160
              : sizePreset === 'login'
                ? 220
                : 200;

    return (
        <div
            className={`flex w-full min-w-0 max-w-full items-center justify-center ${className}`.trim()}
            role="img"
            aria-label={ariaLabel}
        >
            <MealCraftLogo variant="smart" width={lockupWidth} alt={ariaLabel} />
        </div>
    );
}
