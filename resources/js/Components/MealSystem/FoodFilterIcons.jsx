const ICON_BASE = 'block h-4 w-4 shrink-0 bg-current';

/** @type {Record<string, string>} */
const MASK_URLS = {
    dairy: '/images/food-filters/dairy.png',
    gluten: '/images/food-filters/gluten.png',
    eggs: '/images/food-filters/eggs.png',
    soy: '/images/food-filters/soy.png',
    nightshades: '/images/food-filters/nightshades.png',
    beans: '/images/food-filters/beans.png',
    nuts: '/images/food-filters/nuts.png',
    spicy: '/images/food-filters/spicy.png',
    shellfish: '/images/food-filters/shellfish.png',
    fish: '/images/food-filters/fish.png',
    sesame: '/images/food-filters/sesame.png',
    other: '/images/food-filters/other.png',
};

/**
 * Tintable mask glyph from the food-filter reference art.
 *
 * @param {{ className?: string; src: string }} props
 */
function MaskIcon({ className = '', src }) {
    return (
        <span
            role="img"
            aria-hidden="true"
            className={`${ICON_BASE} ${className}`.trim()}
            style={{
                WebkitMaskImage: `url(${src})`,
                maskImage: `url(${src})`,
                WebkitMaskSize: 'contain',
                maskSize: 'contain',
                WebkitMaskRepeat: 'no-repeat',
                maskRepeat: 'no-repeat',
                WebkitMaskPosition: 'center',
                maskPosition: 'center',
            }}
        />
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconDairy({ className = '' }) {
    return <MaskIcon className={className} src={MASK_URLS.dairy} />;
}

/**
 * @param {{ className?: string }} props
 */
export function IconGluten({ className = '' }) {
    return <MaskIcon className={className} src={MASK_URLS.gluten} />;
}

/**
 * @param {{ className?: string }} props
 */
export function IconEggs({ className = '' }) {
    return <MaskIcon className={className} src={MASK_URLS.eggs} />;
}

/**
 * @param {{ className?: string }} props
 */
export function IconSoy({ className = '' }) {
    return <MaskIcon className={className} src={MASK_URLS.soy} />;
}

/**
 * @param {{ className?: string }} props
 */
export function IconNightshades({ className = '' }) {
    return <MaskIcon className={className} src={MASK_URLS.nightshades} />;
}

/**
 * @param {{ className?: string }} props
 */
export function IconBeans({ className = '' }) {
    return <MaskIcon className={className} src={MASK_URLS.beans} />;
}

/**
 * @param {{ className?: string }} props
 */
export function IconNuts({ className = '' }) {
    return <MaskIcon className={className} src={MASK_URLS.nuts} />;
}

/**
 * @param {{ className?: string }} props
 */
export function IconSpicy({ className = '' }) {
    return <MaskIcon className={className} src={MASK_URLS.spicy} />;
}

/**
 * @param {{ className?: string }} props
 */
export function IconShellfish({ className = '' }) {
    return <MaskIcon className={className} src={MASK_URLS.shellfish} />;
}

/**
 * @param {{ className?: string }} props
 */
export function IconFish({ className = '' }) {
    return <MaskIcon className={className} src={MASK_URLS.fish} />;
}

/**
 * @param {{ className?: string }} props
 */
export function IconSesame({ className = '' }) {
    return <MaskIcon className={className} src={MASK_URLS.sesame} />;
}

/**
 * @param {{ className?: string }} props
 */
export function IconOther({ className = '' }) {
    return <MaskIcon className={className} src={MASK_URLS.other} />;
}
