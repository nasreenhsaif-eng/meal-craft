import { useCallback, useState } from 'react';
import { motion } from 'framer-motion';

import { AnimatedSeal } from './AnimatedSeal.jsx';

/** Matches static sprite tagline treatment — keep in sync with `VERTICAL_TAGLINE_OPACITY` in MealCraftLogo.jsx */
export const ANIMATED_TAGLINE_OPACITY = 0.6;

export const ANIMATED_LOGO_GREEN = '#6E8C47';
export const ANIMATED_LOGO_GOLD = '#D8A933';
export const ANIMATED_LOGO_DARK_BG = '#1C2416';
const FONT = '"Baloo 2", system-ui, sans-serif';

/** Tagline uses explicit 0.6 alpha so it stays subordinate to the wordmark (see VERTICAL_TAGLINE_OPACITY) */
function taglineFillStyle(onDarkCanvas) {
    return onDarkCanvas ? 'rgba(255, 255, 255, 0.6)' : 'rgba(110, 140, 71, 0.6)';
}

/**
 * @param {number} containerWidthPx
 * @param {number} [max]
 */
function wordmarkFontSizeForWidth(containerWidthPx, max = 81) {
    if (!Number.isFinite(containerWidthPx) || containerWidthPx <= 0) {
        return max;
    }
    return Math.min(max, containerWidthPx * 0.2);
}

/**
 * @param {{
 *   variant: 'minimal-animated' | 'smart-animated' | 'marketing-animated';
 *   width?: number | string;
 *   className?: string;
 *   alt?: string;
 *   title?: string;
 *   presentation?: 'inline' | 'splash';
 *   onSplashComplete?: () => void;
 * }} props
 */
export function MealCraftLogoAnimatedIdentity({
    variant,
    width,
    className = '',
    alt = 'Meal Craft',
    title,
    presentation = 'inline',
    onSplashComplete,
}) {
    const isSplash = presentation === 'splash' && variant === 'marketing-animated';
    const [exiting, setExiting] = useState(false);

    const onSealCycleEnd = useCallback(() => {
        setExiting(true);
    }, []);

    const pxWidth = typeof width === 'number' && Number.isFinite(width) ? width : null;
    const strWidth = typeof width === 'string' && /^\d+(\.\d+)?px$/.test(width.trim())
        ? Number.parseFloat(width.replace(/px$/i, ''))
        : null;
    const containerPx = pxWidth ?? strWidth ?? (variant === 'minimal-animated' ? 128 : variant === 'smart-animated' ? 219 : 320);

    /** Splash defaults to 430px seal width when `width` is omitted (matches hero spec). */
    const sealSize = isSplash ? pxWidth ?? strWidth ?? 430 : containerPx;

    const wordmarkPx = isSplash ? 81 : wordmarkFontSizeForWidth(containerPx);
    const baseTaglinePx = Math.max(11, wordmarkPx * 0.185);
    /** Smart tier only: tagline +20% presence (still secondary vs wordmark) */
    const taglinePx =
        variant === 'smart-animated' ? Math.round(baseTaglinePx * 1.2 * 100) / 100 : baseTaglinePx;
    const onDarkCanvas = isSplash;
    const wordmarkColor = onDarkCanvas ? '#ffffff' : ANIMATED_LOGO_GREEN;

    const rootClass = ['flex flex-col items-center text-center', className].filter(Boolean).join(' ');

    const columnMaxStyle =
        isSplash
            ? { width: '100%', maxWidth: 'min(430px, 100%)' }
            : pxWidth != null
              ? { width: `${containerPx}px`, maxWidth: `${containerPx}px` }
              : { width: '100%', maxWidth: `${containerPx}px` };

    const inner = (
        <div className="mx-auto flex w-full flex-col items-center justify-center" style={columnMaxStyle}>
            {title ? <span className="sr-only">{title}</span> : null}
            <div className="flex w-full shrink-0 justify-center">
                <AnimatedSeal
                    size={sealSize}
                    background="transparent"
                    onComplete={isSplash ? onSealCycleEnd : undefined}
                    className="max-w-full shrink-0"
                    ariaHidden
                />
            </div>

            {variant === 'minimal-animated' ? null : (
                <div className="flex w-full max-w-full flex-col items-center self-center">
                    <motion.p
                        className="mt-1 w-full max-w-full text-center text-balance"
                        style={{
                            fontFamily: FONT,
                            fontSize: `${wordmarkPx}px`,
                            fontWeight: 700,
                            lineHeight: 1,
                            letterSpacing: '-0.5px',
                            color: wordmarkColor,
                            textAlign: 'center',
                        }}
                        initial={{ opacity: 0, y: 22 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.75, delay: 3.7, ease: [0.22, 1, 0.36, 1] }}
                    >
                        Meal Craft
                    </motion.p>

                    {variant === 'smart-animated' ? (
                        <motion.p
                            className="mt-3 w-full max-w-full text-center uppercase tracking-[0.14em]"
                            style={{
                                fontFamily: FONT,
                                fontSize: `${taglinePx}px`,
                                fontWeight: 600,
                                color: taglineFillStyle(onDarkCanvas),
                                textAlign: 'center',
                            }}
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            transition={{ duration: 0.8, delay: 4.2 }}
                        >
                            SMART KITCHEN
                        </motion.p>
                    ) : (
                        <motion.p
                            className="mt-3 w-full max-w-full text-center text-balance"
                            style={{
                                fontFamily: FONT,
                                fontSize: `${taglinePx}px`,
                                fontWeight: 600,
                                letterSpacing: '3.5px',
                                color: taglineFillStyle(onDarkCanvas),
                                textAlign: 'center',
                            }}
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            transition={{ duration: 0.8, delay: 4.2 }}
                        >
                            ANTI-INFLAMMATORY · SMART KITCHEN
                        </motion.p>
                    )}
                </div>
            )}

            {isSplash ? (
                <motion.p
                    className="absolute bottom-8 left-1/2 -translate-x-1/2"
                    style={{
                        fontFamily: FONT,
                        fontSize: '14px',
                        color: 'rgba(255,255,255,0.22)',
                        letterSpacing: '0.2em',
                    }}
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ duration: 1.2, delay: 4.6 }}
                >
                    CLICK TO CONTINUE
                </motion.p>
            ) : null}
        </div>
    );

    if (isSplash) {
        return (
            <motion.div
                role="img"
                aria-label={alt}
                data-meal-craft-logo
                data-variant={variant}
                className={`fixed inset-0 z-50 flex select-none flex-col items-center justify-center overflow-hidden px-4 ${rootClass}`}
                style={{ background: ANIMATED_LOGO_DARK_BG, cursor: 'pointer' }}
                animate={{ opacity: exiting ? 0 : 1 }}
                transition={{ duration: 0.9, ease: 'easeInOut' }}
                onAnimationComplete={() => {
                    if (exiting) {
                        onSplashComplete?.();
                    }
                }}
                onClick={() => setExiting(true)}
            >
                <div className="relative flex w-full max-w-[min(430px,100%)] flex-col items-center justify-center">
                    {inner}
                </div>
            </motion.div>
        );
    }

    return (
        <div
            role="img"
            aria-label={alt}
            data-meal-craft-logo
            data-variant={variant}
            data-tagline-opacity={variant !== 'minimal-animated' ? ANIMATED_TAGLINE_OPACITY : undefined}
            className={rootClass}
        >
            {inner}
        </div>
    );
}
