import { MealCraftLogoAnimatedIdentity } from './MealCraftLogoAnimated.jsx';

/** Root-relative URL — matches Laravel `public/` and Storybook `staticDirs` (`.storybook/main.js`). */
const logoSheet = '/images/branding/logo-sheet.svg';

/**
 * Inline Motion-powered identity (Framer paths); not sprite-backed.
 *
 * Smart tier (`smart-animated`): typography lives in `MealCraftLogoAnimated.jsx` — wordmark + **SMART KITCHEN**
 * in all caps, tagline ~20% larger than baseline, colour uses rgba(..., {@link VERTICAL_TAGLINE_OPACITY}) on light UI.
 * Seal veins use `AnimatedSeal.jsx` clip-path + butt caps against the leaf outline path id `*-leafOutline`.
 */
export const ANIMATED_LOGO_VARIANTS = ['minimal-animated', 'smart-animated', 'marketing-animated'];

const ANIMATED_LOGO_VARIANT_SET = new Set(ANIMATED_LOGO_VARIANTS);

/**
 * Stacked lockups in `logo-sheet.svg` (sprite rects). `vertical-*` slice from embedded vector groups.
 *
 * Secondary taglines — “Smart Kitchen” and the anti-inflammatory/messaging line are **separate** copy;
 * they use opacity **0.6 in the SVG** so they rasterize
 * correctly when the sheet is painted via `<image>`. {@link VERTICAL_TAGLINE_OPACITY} is the design token;
 * it cannot be applied to sub-layers of a rasterized SVG `<image>` from JSX alone.
 */
export const VERTICAL_TAGLINE_OPACITY = 0.6;

/** Sprite slices where tagline(s) use opacity 0.6 in the exported artwork */
const VARIANTS_WITH_MUTED_TAGLINE_IN_ARTWORK = new Set([
    'smart',
    'standard',
    'marketing',
    'vertical-smart',
    'vertical-marketing',
]);

/**
 * Master sprite (1340×1260): leaf row · logos · seals · vertical lockups (extra canvas below y≈870 for marketing).
 * Must match `public/images/branding/logo-sheet.svg`.
 */
export const LOGO_SHEET_WIDTH = 1340;
export const LOGO_SHEET_HEIGHT = 1260;

/**
 * Lockup tiers (same semantics for horizontal + vertical):
 * - minimal — seal + “Meal Craft”
 * - smart — + “Smart Kitchen” only (not the anti-inflammatory line)
 * - marketing — + both taglines: anti-inflammatory/messaging line **and** “Smart Kitchen” (distinct lines; full strip on horizontal)
 */
export const LOGO_VIEWBOX = {
    /** Horizontal minimal */
    minimal: '20 217 128 30',
    /**
     * Horizontal smart — seal + wordmark + “Smart Kitchen” (tagline in sprite).
     * Sheet clip is 219×68; wordmark extends to ~×531 in sheet space, so width must stay
     * ≥ ~217 or letterforms (e.g. “Craft”) get clipped and show a vertical edge. We trim
     * only ~2 units off the clip’s right padding to reduce sprite bleed without cropping type.
     */
    smart: '315 204 217 68',
    /** @deprecated Use `smart` */
    standard: '315 204 217 68',
    /** Horizontal marketing */
    marketing: '933 167 387 130',
    /** Vertical minimal */
    'vertical-minimal': '222 660 280 180',
    /** @deprecated Use `vertical-minimal` */
    'vertical-standard': '222 660 280 180',
    /** Vertical smart */
    'vertical-smart': '222 840 280 220',
    /** Vertical marketing */
    'vertical-marketing': '470 870 400 380',
};

/**
 * Seal scales — inside `translate(0 317)` in logo-sheet.svg.
 * seal-xl: full ring + strokes — keep ~10+ user units pad L/R of outer gold stroke to avoid subpixel clip.
 */
export const SEAL_VIEWBOX = {
    'seal-xs': '72 515 84 84',
    'seal-sm': '186 481 152 152',
    'seal-md': '368 473 168 168',
    'seal-lg': '566 431 252 252',
    'seal-xl': '836 348 448 428',
};

/** Leaf tints — integer rects aligned with sprite artwork (named tokens only, no hex API) */
export const LEAF_VIEWBOX_BY_COLOR = {
    gold: '57 30 33 87',
    red: '355 30 33 87',
    purple: '653 30 33 87',
    green: '951 30 33 87',
    blue: '1249 30 33 87',
};

/** Product labels for the five leaf tokens (Storybook / UI copy) */
export const LEAF_LABEL_BY_COLOR = {
    gold: 'Safe',
    red: 'Warning',
    purple: 'Premium',
    green: 'Fresh',
    blue: 'Balance',
};

const KNOWN_LEAF_COLORS = ['gold', 'red', 'purple', 'green', 'blue'];

/** Storybook — single variant dropdown for sprite slices */
export const MEAL_CRAFT_LOGO_VARIANT_OPTIONS = [
    'minimal',
    'smart',
    'marketing',
    'standard',
    'vertical-minimal',
    'vertical-smart',
    'vertical-marketing',
    'vertical-standard',
    'seal-xs',
    'seal-sm',
    'seal-md',
    'seal-lg',
    'seal-xl',
    'leaf',
    ...ANIMATED_LOGO_VARIANTS,
];

/** Re-export for Storybook (`*.stories.jsx`) and docs */
export const mealCraftLogoArgTypes = {
    variant: {
        control: 'select',
        options: MEAL_CRAFT_LOGO_VARIANT_OPTIONS,
        description: 'Sprite slice from logo-sheet.svg, or Motion animated tier (`*-animated`)',
    },
    presentation: {
        control: 'inline-radio',
        options: ['inline', 'splash'],
        description: 'Splash fullscreen overlay — only `marketing-animated`',
        if: { arg: 'variant', eq: 'marketing-animated' },
    },
    color: {
        control: 'select',
        options: KNOWN_LEAF_COLORS,
        if: { arg: 'variant', eq: 'leaf' },
        description: 'Leaf tint (variant leaf only)',
    },
    width: { control: { type: 'number' } },
    alt: { control: 'text' },
};

/** Combined lookup for tooling / Storybook */
export const VARIANT_VIEWBOX = {
    ...LOGO_VIEWBOX,
    ...SEAL_VIEWBOX,
    leaf: LEAF_VIEWBOX_BY_COLOR.gold,
};

/** @param {unknown} raw */
function resolveLeafColor(raw) {
    const c = String(raw ?? 'gold')
        .trim()
        .toLowerCase();
    const aliases = {
        gold: 'gold',
        yellow: 'gold',
        amber: 'gold',
        red: 'red',
        crimson: 'red',
        rose: 'red',
        purple: 'purple',
        violet: 'purple',
        magenta: 'purple',
        green: 'green',
        lime: 'green',
        primary: 'green',
        blue: 'blue',
        navy: 'blue',
        indigo: 'blue',
    };

    if (aliases[c]) {
        return aliases[c];
    }
    if (KNOWN_LEAF_COLORS.includes(c)) {
        return c;
    }

    return 'gold';
}

/** @param {unknown} variant @param {unknown} color */
export function resolveSpriteViewBox(variant, color) {
    const v = String(variant ?? 'smart')
        .trim()
        .toLowerCase();

    if (v === 'leaf') {
        const shade = resolveLeafColor(color);

        return LEAF_VIEWBOX_BY_COLOR[shade] ?? LEAF_VIEWBOX_BY_COLOR.gold;
    }

    if (Object.prototype.hasOwnProperty.call(SEAL_VIEWBOX, v)) {
        return SEAL_VIEWBOX[v];
    }

    if (Object.prototype.hasOwnProperty.call(LOGO_VIEWBOX, v)) {
        return LOGO_VIEWBOX[v];
    }

    return LOGO_VIEWBOX.smart;
}

function parseViewBox(vb) {
    const [, , vw, vh] = vb.trim().split(/\s+/).map(Number);

    return { vw, vh, aspect: vw / vh };
}

function parseCssLengthToPx(value) {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return value;
    }
    if (typeof value !== 'string') {
        return null;
    }
    const t = value.trim();
    if (/^\d+(\.\d+)?$/.test(t)) {
        return Number.parseFloat(t);
    }
    if (/^\d+(\.\d+)?px$/i.test(t)) {
        return Number.parseFloat(t.replace(/px$/i, ''));
    }

    return null;
}

/**
 * Meal Craft sprites from `logo-sheet.svg` (viewport slices). Vertical stacks share one sheet `<image>`.
 *
 * @param {{
 *   variant?: 'minimal' | 'smart' | 'marketing' | 'standard' | 'vertical-minimal' | 'vertical-smart' | 'vertical-marketing' | 'vertical-standard' | 'seal-xs' | 'seal-sm' | 'seal-md' | 'seal-lg' | 'seal-xl' | 'leaf' | 'minimal-animated' | 'smart-animated' | 'marketing-animated' | string;
 *   color?: string;
 *   width?: number | string;
 *   className?: string;
 *   alt?: string;
 *   title?: string;
 *   presentation?: 'inline' | 'splash';
 *   onSplashComplete?: () => void;
 * }} props
 */
export default function MealCraftLogo({
    variant = 'smart',
    color,
    width,
    className = '',
    alt = 'Meal Craft',
    title,
    presentation = 'inline',
    onSplashComplete,
}) {
    const vKey = String(variant ?? 'smart')
        .trim()
        .toLowerCase();

    if (ANIMATED_LOGO_VARIANT_SET.has(vKey)) {
        const splash =
            vKey === 'marketing-animated' && String(presentation).toLowerCase() === 'splash';

        return (
            <MealCraftLogoAnimatedIdentity
                variant={vKey}
                width={width}
                className={className}
                alt={alt}
                title={title}
                presentation={splash ? 'splash' : 'inline'}
                onSplashComplete={onSplashComplete}
            />
        );
    }

    const taglineMutedInArtwork = VARIANTS_WITH_MUTED_TAGLINE_IN_ARTWORK.has(vKey);

    const vb = resolveSpriteViewBox(variant, color);
    const { vw, vh, aspect } = parseViewBox(vb);

    const resolvedWidth = width ?? vw;
    const pxWidth = parseCssLengthToPx(resolvedWidth);

    const widthStyle =
        typeof resolvedWidth === 'number'
            ? `${resolvedWidth}px`
            : typeof resolvedWidth === 'string'
              ? resolvedWidth
              : `${vw}px`;

    const rootSvgStyle = {
        fill: 'none',
        /** Clip to viewBox — full-sheet `<image>` must match sprite dimensions (1340×1260). */
        overflow: 'hidden',
        contain: 'paint',
        ...(pxWidth != null
            ? {}
            : {
                  width: widthStyle,
                  height: 'auto',
                  aspectRatio: `${vw} / ${vh}`,
                  maxWidth: '100%',
              }),
    };

    return (
        <span className="inline-flex max-w-full min-w-0 overflow-hidden">
            <svg
                data-meal-craft-logo
                data-tagline-opacity={taglineMutedInArtwork ? VERTICAL_TAGLINE_OPACITY : undefined}
                className={['block shrink-0 max-w-full overflow-hidden', className].filter(Boolean).join(' ')}
                viewBox={vb}
                role="img"
                aria-label={alt}
                xmlns="http://www.w3.org/2000/svg"
                width={pxWidth != null ? pxWidth : undefined}
                height={pxWidth != null ? pxWidth / aspect : undefined}
                style={rootSvgStyle}
                preserveAspectRatio="xMidYMid meet"
            >
                {title ? <title>{title}</title> : null}
                <image
                    href={logoSheet}
                    x={0}
                    y={0}
                    width={LOGO_SHEET_WIDTH}
                    height={LOGO_SHEET_HEIGHT}
                    preserveAspectRatio="none"
                />
            </svg>
        </span>
    );
}
