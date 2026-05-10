/**
 * Meal Craft — Animated Seal
 *
 * Self-contained craft seal (Framer Motion). Uses `framer-motion` (project dependency).
 * Seal artwork is fully transparent — no ambient fills behind the rings.
 *
 * @example
 * // Once, transparent — drop anywhere
 * <AnimatedSeal />
 *
 * @example
 * // Looping hero badge
 * <AnimatedSeal size={240} loop />
 *
 * @example
 * // Splash — onComplete after first cycle (~5.6s); optional wrapper colour via `background`
 * <AnimatedSeal size={430} background="#1C2416" onComplete={() => setReady(true)} />
 */

import { useEffect, useId, useState } from 'react';
import { motion } from 'framer-motion';

const GREEN = '#6E8C47';
const GOLD = '#D8A933';

/** ViewBox width — wider than legacy 430 so ring strokes aren’t clipped left/right */
const VIEWBOX_MIN_X = -10;
const VIEWBOX_W = 450;
/** Extra vertical padding so ring strokes / pulse scale aren’t clipped */
const VIEWBOX_MIN_Y = -10;
const VIEWBOX_H = 430;

const CX = 215;
const CY = 205;
const SC = 2.73;

const vx = (v) => CX + (v - 100) * SC;
const vy = (v) => CY + (v - 110) * SC;

const LEAF_D = `
  M ${vx(100)} ${vy(158)}
  C ${vx(115)} ${vy(140)}, ${vx(122)} ${vy(116)}, ${vx(117)} ${vy(94)}
  C ${vx(112)} ${vy(80)},  ${vx(100)} ${vy(73)},  ${vx(100)} ${vy(73)}
  C ${vx(100)} ${vy(73)},  ${vx(88)}  ${vy(80)},  ${vx(83)} ${vy(94)}
  C ${vx(78)}  ${vy(116)}, ${vx(85)} ${vy(140)}, ${vx(100)} ${vy(158)} Z
`;

/** @param {{ d: string; stroke: string; strokeWidth: number; delay: number; duration?: number; opacity?: number; strokeLinecap?: 'butt' | 'round' | 'square' }} props */
function DrawPath({
    d,
    stroke,
    strokeWidth,
    delay,
    duration = 0.4,
    opacity = 1,
    strokeLinecap = 'round',
}) {
    return (
        <motion.path
            d={d}
            stroke={stroke}
            strokeWidth={strokeWidth}
            strokeLinecap={strokeLinecap}
            fill="none"
            initial={{ pathLength: 0, opacity: 0 }}
            animate={{ pathLength: 1, opacity }}
            transition={{
                pathLength: { duration, delay, ease: [0.4, 0, 0.2, 1] },
                opacity: { duration: 0.01, delay },
            }}
        />
    );
}

/**
 * @param {{
 *   uid: string;
 *   size: number;
 *   ariaHidden?: boolean;
 * }} props
 */
function SealCore({ uid, size, ariaHidden = false }) {
    const h = Math.round(VIEWBOX_H * (size / VIEWBOX_W));
    const leafClipId = `${uid}-leafClip`;

    return (
        <svg
            width={size}
            height={h}
            viewBox={`${VIEWBOX_MIN_X} ${VIEWBOX_MIN_Y} ${VIEWBOX_W} ${VIEWBOX_H}`}
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            style={{
                display: 'block',
                maxWidth: '100%',
                height: 'auto',
                shapeRendering: 'geometricPrecision',
                overflow: 'visible',
            }}
            aria-hidden={ariaHidden ? true : undefined}
            aria-label={ariaHidden ? undefined : 'Meal Craft animated seal'}
            role={ariaHidden ? undefined : 'img'}
        >
            <defs>
                <clipPath id={leafClipId} clipPathUnits="userSpaceOnUse">
                    <path id={`${uid}-leafOutline`} d={LEAF_D.trim()} />
                </clipPath>
            </defs>

            <motion.circle
                cx={CX}
                cy={CY}
                r={65 * SC}
                stroke={GOLD}
                strokeWidth={2.5 * SC}
                fill="none"
                initial={{ pathLength: 0, opacity: 0 }}
                animate={{ pathLength: 1, opacity: 1 }}
                transition={{
                    pathLength: { duration: 1.4, delay: 0.1, ease: [0.4, 0, 0.2, 1] },
                    opacity: { duration: 0.01, delay: 0.1 },
                }}
            />

            <motion.circle
                cx={CX}
                cy={CY}
                r={55 * SC}
                stroke={GOLD}
                strokeWidth={1 * SC}
                fill="none"
                initial={{ pathLength: 0, opacity: 0 }}
                animate={{ pathLength: 1, opacity: 0.5 }}
                transition={{
                    pathLength: { duration: 1.2, delay: 0.45, ease: [0.4, 0, 0.2, 1] },
                    opacity: { duration: 0.01, delay: 0.45 },
                }}
            />

            {[
                [CX, CY - 65 * SC],
                [CX + 65 * SC, CY],
                [CX - 65 * SC, CY],
                [CX, CY + 65 * SC],
            ].map(([x, y], i) => (
                <motion.circle
                    key={i}
                    cx={x}
                    cy={y}
                    r={3 * SC}
                    fill={GOLD}
                    initial={{ scale: 0, opacity: 0 }}
                    animate={{ scale: 1, opacity: 1 }}
                    style={{ transformOrigin: `${x}px ${y}px` }}
                    transition={{ type: 'spring', stiffness: 380, damping: 14, delay: 0.9 + i * 0.1 }}
                />
            ))}

            <motion.circle
                cx={CX}
                cy={CY}
                r={65 * SC}
                stroke={GOLD}
                strokeWidth={1.5 * SC}
                fill="none"
                initial={{ scale: 1, opacity: 0 }}
                animate={{ scale: [1, 1.09, 1.09], opacity: [0, 0.45, 0] }}
                style={{ transformOrigin: `${CX}px ${CY}px` }}
                transition={{ duration: 1.4, delay: 3.4, ease: 'easeOut' }}
            />

            <DrawPath
                d={`M ${vx(100)} ${vy(158)} L ${vx(100)} ${vy(172)}`}
                stroke={GREEN}
                strokeWidth={1.3 * SC}
                opacity={0.3}
                delay={1.25}
                duration={0.3}
            />
            <DrawPath
                d={`M ${vx(104)} ${vy(158)} C ${vx(122)} ${vy(162)}, ${vx(82)} ${vy(168)}, ${vx(100)} ${vy(172)}`}
                stroke={GREEN}
                strokeWidth={1.7 * SC}
                opacity={0.45}
                delay={1.35}
                duration={0.5}
            />
            <DrawPath
                d={`M ${vx(95)} ${vy(165)} L ${vx(105)} ${vy(165)}`}
                stroke={GREEN}
                strokeWidth={0.8 * SC}
                opacity={0.32}
                delay={1.6}
                duration={0.12}
            />
            <DrawPath
                d={`M ${vx(96)} ${vy(158)} C ${vx(78)} ${vy(162)}, ${vx(118)} ${vy(168)}, ${vx(100)} ${vy(172)}`}
                stroke={GREEN}
                strokeWidth={1.7 * SC}
                opacity={0.85}
                delay={1.42}
                duration={0.5}
            />

            <motion.path
                d={LEAF_D}
                fill={GREEN}
                style={{ transformOrigin: `${vx(100)}px ${vy(158)}px` }}
                initial={{ scaleY: 0, opacity: 0 }}
                animate={{ scaleY: 1, opacity: 1 }}
                transition={{
                    scaleY: { type: 'spring', stiffness: 110, damping: 16, delay: 1.38 },
                    opacity: { duration: 0.05, delay: 1.38 },
                }}
            />

            <g clipPath={`url(#${leafClipId})`}>
                <DrawPath
                    d={`M ${vx(100)} ${vy(157)} L ${vx(100)} ${vy(74)}`}
                    stroke="rgba(255,255,255,0.55)"
                    strokeWidth={1.45 * SC}
                    strokeLinecap="butt"
                    delay={2.05}
                    duration={0.5}
                />
                {[
                    [`M ${vx(100)} ${vy(139)} L ${vx(80)}  ${vy(134)}`, `M ${vx(100)} ${vy(139)} L ${vx(120)} ${vy(134)}`],
                    [`M ${vx(100)} ${vy(126)} L ${vx(76)}  ${vy(120)}`, `M ${vx(100)} ${vy(126)} L ${vx(124)} ${vy(120)}`],
                    [`M ${vx(100)} ${vy(112)} L ${vx(75)}  ${vy(105)}`, `M ${vx(100)} ${vy(112)} L ${vx(125)} ${vy(105)}`],
                    [`M ${vx(100)} ${vy(97)}  L ${vx(77)}  ${vy(88)}`, `M ${vx(100)} ${vy(97)}  L ${vx(123)} ${vy(88)}`],
                ].map(([l, r], i) => (
                    <g key={i}>
                        {[l, r].map((path, j) => (
                            <DrawPath
                                key={j}
                                d={path}
                                stroke="rgba(255,255,255,0.5)"
                                strokeWidth={1 * SC}
                                strokeLinecap="butt"
                                delay={2.4 + i * 0.13}
                                duration={0.24}
                            />
                        ))}
                    </g>
                ))}
            </g>
        </svg>
    );
}

/** Duration of one full animation cycle in milliseconds */
export const ANIMATED_SEAL_CYCLE_MS = 5600;

/**
 * @param {{
 *   size?: number;
 *   loop?: boolean;
 *   background?: string;
 *   onComplete?: () => void;
 *   className?: string;
 *   ariaHidden?: boolean;
 * }} props
 */
export function AnimatedSeal({
    size = 430,
    loop = false,
    background = 'transparent',
    onComplete,
    className = '',
    ariaHidden = false,
}) {
    const baseUid = useId().replace(/:/g, 's');
    const [cycle, setCycle] = useState(0);

    useEffect(() => {
        if (!loop) {
            return undefined;
        }
        const t = setInterval(() => setCycle((c) => c + 1), ANIMATED_SEAL_CYCLE_MS);
        return () => clearInterval(t);
    }, [loop]);

    useEffect(() => {
        if (loop || !onComplete) {
            return undefined;
        }
        const t = setTimeout(onComplete, ANIMATED_SEAL_CYCLE_MS);
        return () => clearTimeout(t);
    }, [loop, onComplete]);

    return (
        <div className={className} style={{ display: 'inline-block', background, lineHeight: 0 }}>
            <SealCore key={`${baseUid}-${cycle}`} uid={`${baseUid}-${cycle}`} size={size} ariaHidden={ariaHidden} />
        </div>
    );
}
