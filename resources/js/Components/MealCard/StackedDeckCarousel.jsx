import { cloneElement, isValidElement, useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { animate, motion, useMotionValue, useMotionValueEvent, useTransform } from 'framer-motion';

/** @typedef {-1 | 1} DeckDirection */

const FRONT_SPEC = Object.freeze({
    z: 100,
    x: 0,
    scale: 1,
    blur: 0,
});

/** @param {number} depth 1 = nearest behind front; larger = farther back */
function getBackSpec(depth) {
    const d = Math.max(1, Math.floor(depth));

    return {
        z: 100 - d * 10,
        x: -25 * d,
        scale: Math.max(0.74, 1 - 0.075 * d),
        blur: Math.min(2 * d, 12),
    };
}

/** Max decorative layers behind the front card (performance cap for large menus). */
const MAX_BACK_STACK_LAYERS = 8;

/**
 * Decorative cards behind the hero = one fewer than options (n−1). Single option ⇒ no stack behind.
 *
 * @param {number} itemCount
 */
function visibleBackLayerCount(itemCount) {
    if (itemCount <= 1) {
        return 0;
    }

    return Math.min(itemCount - 1, MAX_BACK_STACK_LAYERS);
}

/** Deepest stack slot z-index while exit clone rejoins the fan (below BG2 idle layer). */
const BACK_REJOIN_Z = 10;

function springStrong() {
    return { type: 'spring', stiffness: 300, damping: 30, mass: 0.72 };
}

/** Drift into back-of-stack slot — avoids robotic snapping */
function springSoft() {
    return { type: 'spring', stiffness: 150, damping: 20, mass: 0.85 };
}

/** Desktop row slide — direction follows arrow, not index wrap */
function netflixSlideTransition() {
    return { type: 'tween', duration: 0.48, ease: [0.22, 1, 0.36, 1] };
}

/** Desktop infinite ribbon: three identical copies in the track; logical slides use the middle copy only. */
const DESKTOP_RIBBON_COPIES = 3;

/**
 * Full scroll width of the horizontal ribbon. Uses first→last screen span so transform on the track cannot
 * shrink the measured extent; underestimating triggers `tw <= cw` centering and a massive empty band.
 *
 * @param {HTMLDivElement} track
 */
function getDesktopTrackScrollExtent(track) {
    const n = track.children.length;
    if (n === 0) {
        return Math.ceil(Math.max(track.scrollWidth, track.offsetWidth));
    }

    const first = /** @type {HTMLElement} */ (track.children[0]).getBoundingClientRect();
    const last = /** @type {HTMLElement} */ (track.children[n - 1]).getBoundingClientRect();
    const span = last.right - first.left;

    return Math.ceil(Math.max(track.scrollWidth, track.offsetWidth, span));
}

/** Shared shell for each desktop slide cell — scaled up from the 270px mobile deck baseline */
const DESKTOP_CARD_SHELL =
    'flex min-h-[232px] w-[270px] shrink-0 flex-col items-stretch sm:min-h-[248px] sm:w-[286px] lg:min-h-[264px] lg:w-[302px] transform-gpu';

/** Static row (1–2 items): side-by-side, centered — no carousel chrome */
const STATIC_CARD_SHELL_SINGLE =
    'flex min-h-[232px] w-full max-w-[302px] shrink-0 flex-col items-stretch sm:min-h-[248px] sm:w-[286px] lg:min-h-[264px] lg:w-[302px] transform-gpu';

/** Two-up row: each card takes half the row (minus gap) so they stay horizontal on mobile */
const STATIC_CARD_SHELL_PAIR =
    'flex min-h-[232px] w-[calc((100%-1rem)/2)] min-w-0 max-w-[302px] shrink-0 flex-col items-stretch sm:min-h-[248px] sm:w-[calc((100%-1.5rem)/2)] lg:min-h-[264px] lg:max-w-[302px] transform-gpu';

const DESKTOP_MEDIA = '(min-width: 768px)';

function useIsDesktopLayout() {
    const [matches, setMatches] = useState(() =>
        typeof window !== 'undefined' ? window.matchMedia(DESKTOP_MEDIA).matches : false,
    );

    useEffect(() => {
        const mq = window.matchMedia(DESKTOP_MEDIA);
        const sync = () => setMatches(Boolean(mq.matches));
        sync();
        mq.addEventListener('change', sync);
        return () => mq.removeEventListener('change', sync);
    }, []);

    return matches;
}

/**
 * Animated back-of-stack layer (mobile deck). One hook bundle per depth instance.
 *
 * @param {object} props
 * @param {number} props.depth
 * @param {import('motion').MotionValue<number>} props.xRaw
 * @param {import('react').ReactNode} props.children
 */
function MobileDeckBackLayer({ depth, xRaw, children }) {
    const spec = useMemo(() => getBackSpec(depth), [depth]);
    const x = useTransform(xRaw, (lx) => spec.x + lx * (0.035 + depth * 0.012));
    const rotate = useTransform(xRaw, (lx) => lx * -(0.008 + depth * 0.003));

    return (
        <motion.div
            aria-hidden="true"
            className="pointer-events-none absolute left-0 top-0 w-full transform-gpu"
            style={{
                zIndex: spec.z,
                x,
                rotate,
                scale: spec.scale,
                filter: `blur(${spec.blur}px)`,
                translateZ: 0,
            }}
            transition={springSoft()}
        >
            {children}
        </motion.div>
    );
}

/**
 * High-end stacked deck carousel with circular index and return-to-back exit.
 *
 * Selection state is owned by the parent: pass `selected` / `onToggleSelected` from `renderCard`.
 *
 * @template T
 * @param {object} props
 * @param {string} [props.title]
 * @param {T[]} [props.items]
 * @param {T[]} [props.meals] Alias for `items` (live data from parent).
 * @param {string} [props.deckScopeKey] When this identity changes (e.g. day + slot), deck indices reset without breaking circular wrap logic.
 * @param {(item: T, index: number) => string} props.getKey
 * @param {(item: T, index: number, ctx: { isFront: boolean, stackPos: (1|2|3|null), deckLayout: 'ribbon'|'stack' }) => import('react').ReactNode} props.renderCard
 */
export default function StackedDeckCarousel({ title: _title, items: itemsProp, meals, getKey, renderCard, deckScopeKey }) {
    const items = meals ?? itemsProp ?? [];
    const [activeIndex, setActiveIndex] = useState(0);
    const itemCount = items.length;

    const backLayerCount = useMemo(() => visibleBackLayerCount(itemCount), [itemCount]);

    const deckScopePrevRef = useRef(/** @type {string|undefined} */ (undefined));

    /** Bumps when gallery viewport size changes so the slide track recenters */
    const [galleryResizeSeq, setGalleryResizeSeq] = useState(0);

    /** Desktop linear index (>=768px only): (i+1)%n / (i-1+n)%n; separate from mobile `activeIndex` */
    const [desktopActiveIndex, setDesktopActiveIndex] = useState(0);

    const isDesktopLayout = useIsDesktopLayout();

    const desktopGalleryRef = useRef(/** @type {HTMLDivElement|null} */ (null));
    const desktopTrackRef = useRef(/** @type {HTMLDivElement|null} */ (null));
    const desktopCardRefs = useRef(/** @type {(HTMLDivElement|null)[]} */ ([]));
    const desktopTrackX = useMotionValue(0);
    const desktopActiveIndexRef = useRef(0);
    const desktopNavBusyRef = useRef(false);

    useEffect(() => {
        desktopActiveIndexRef.current = desktopActiveIndex;
    }, [desktopActiveIndex]);

    /** When set: we're animating exiting card clone; promotes next/prev visually */
    const [exitSession, setExitSession] = useState(/** @type {null | { fromIndex: number, dir: DeckDirection }} */ (null));
    const [exitZIndex, setExitZIndex] = useState(105);

    const isThrowingRef = useRef(false);

    /** Exit clone overlay */
    const exitX = useMotionValue(0);
    const exitScale = useMotionValue(FRONT_SPEC.scale);
    const exitBlur = useMotionValue(FRONT_SPEC.blur);
    /** z handled by style (number) animated via animate fallback */

    /** Declared before deck reset effect — that effect calls `xRaw.set(0)`. */
    const xRaw = useMotionValue(0);

    useEffect(() => {
        if (itemCount <= 1) {
            setActiveIndex(0);
            return;
        }
        setActiveIndex((i) => Math.min(i, itemCount - 1));
    }, [itemCount]);

    /**
     * Reset slide indices when the deck context changes (category / slot). Keeps modulo wrap logic intact
     * for the new item list without carrying over an out-of-range index.
     */
    useEffect(() => {
        if (deckScopeKey === undefined) {
            deckScopePrevRef.current = undefined;

            return;
        }
        if (deckScopePrevRef.current === deckScopeKey) {
            return;
        }
        deckScopePrevRef.current = deckScopeKey;
        setActiveIndex(0);
        setDesktopActiveIndex(0);
        xRaw.set(0);
        exitX.set(0);
        setExitSession(null);
        isThrowingRef.current = false;
    }, [deckScopeKey]);

    useEffect(() => {
        if (!isDesktopLayout) {
            return;
        }
        setDesktopActiveIndex((i) => Math.min(i, Math.max(0, itemCount - 1)));
    }, [isDesktopLayout, itemCount]);

    const modIndex = useMemo(() => {
        return (idx) => {
            if (itemCount === 0) {
                return 0;
            }
            return ((idx % itemCount) + itemCount) % itemCount;
        };
    }, [itemCount]);

    // === Physics: front card drag (xRaw declared above) ===
    const activeIndexRef = useRef(0);
    const [mobileDotSwipeIdx, setMobileDotSwipeIdx] = useState(/** @type {number|null} */ (null));

    useEffect(() => {
        activeIndexRef.current = activeIndex;
    }, [activeIndex]);

    /** Mobile: highlight the dot for the slide we're swiping toward while dragging. */
    useMotionValueEvent(xRaw, 'change', (latest) => {
        if (itemCount <= 1 || isDesktopLayout) {
            return;
        }
        const ai = activeIndexRef.current;
        const threshold = 28;
        if (latest < -threshold) {
            setMobileDotSwipeIdx(modIndex(ai - 1));
        } else if (latest > threshold) {
            setMobileDotSwipeIdx(modIndex(ai + 1));
        } else {
            setMobileDotSwipeIdx(null);
        }
    });

    useEffect(() => {
        setMobileDotSwipeIdx(null);
    }, [activeIndex, deckScopeKey]);

    useEffect(() => {
        if (exitSession !== null) {
            setMobileDotSwipeIdx(null);
        }
    }, [exitSession]);
    const rotate = useTransform(xRaw, [-220, 0, 220], [-8, 0, 8]);
    const dragScale = useTransform(xRaw, [-220, 0, 220], [0.995, 1, 0.995]);

    const offscreenThreshold = typeof window !== 'undefined' ? -Math.min(900, window.innerWidth + 80) : -900;
    const offscreenRight = typeof window !== 'undefined' ? Math.min(900, window.innerWidth + 80) : 900;

    /**
     * @param {DeckDirection} dir Next (+1) → exit RIGHT then drift to back-left; Prev (−1) → exit LEFT then drift to back-left.
     */
    const performReturnToBack = async (dir) => {
        if (itemCount <= 1) {
            return;
        }
        if (isThrowingRef.current) {
            return;
        }
        isThrowingRef.current = true;

        const fromIndex = activeIndex;
        const totalMeals = itemCount;
        const nextIndex = dir === 1 ? (fromIndex + 1) % totalMeals : (fromIndex - 1 + totalMeals) % totalMeals;

        const exitToward = dir === 1 ? offscreenRight : offscreenThreshold;

        // Start overlay session — main draggable front hides until complete
        setExitZIndex(105);
        setExitSession({ fromIndex, dir });

        exitX.set(0);
        exitScale.set(FRONT_SPEC.scale);
        exitBlur.set(FRONT_SPEC.blur);

        // Reset drag MV so promoted card draws clean
        xRaw.set(0);

        await new Promise((r) => requestAnimationFrame(r));

        // 1) Fly off-screen (next → right; prev → left)
        await animate(exitX, exitToward, springStrong()).finished;

        // 2) While off-screen: drop behind stack, match deepest back-card look
        setExitZIndex(BACK_REJOIN_Z);
        const deepestSpec = backLayerCount >= 1 ? getBackSpec(backLayerCount) : getBackSpec(1);
        exitScale.set(deepestSpec.scale);
        exitBlur.set(deepestSpec.blur);

        // 3) Drift into back-left fan slot (soft spring — not a hard snap)
        await animate(exitX, deepestSpec.x, springSoft()).finished;

        // 4) Commit circular index — worm updates; promoted middle→hero visible during session
        setActiveIndex(nextIndex);

        await new Promise((r) => requestAnimationFrame(r));

        setExitSession(null);
        exitX.set(0);
        exitScale.set(FRONT_SPEC.scale);
        exitBlur.set(FRONT_SPEC.blur);
        xRaw.set(0);
        isThrowingRef.current = false;
    };

    const nudgeThenThrow = async (dir) => {
        if (itemCount <= 1 || isThrowingRef.current) {
            return;
        }
        // Anticipate the throw: Next nudges right; Prev nudges left (match fly-off axis)
        const nudge = dir === 1 ? 18 : -18;
        xRaw.set(0);
        await animate(xRaw, nudge, springSoft()).finished;
        await performReturnToBack(dir);
    };

    /** Promoted card index during transition */
    const promotedIndex =
        exitSession == null ? null : exitSession.dir === 1 ? modIndex(exitSession.fromIndex + 1) : modIndex(exitSession.fromIndex - 1);

    const exitFilter = useTransform(exitBlur, (b) => `blur(${b}px)`);

    /**
     * Parent owns selection; clone only to guarantee handlers exist for breakpoint toggles.
     *
     * @param {T} item
     * @param {number} idx
     * @param {{ isFront: boolean, stackPos: (1|2|3|null) }} ctx
     */
    const renderMealCard = (item, idx, ctx) => {
        const el = renderCard(item, idx, ctx);
        if (!isValidElement(el)) {
            return el;
        }

        return cloneElement(el, {
            selected: Boolean(el.props.selected),
            onToggleSelected: () => {
                el.props.onToggleSelected?.();
            },
        });
    };

    const slideAriaLabel = (item, idx) => {
        if (item && typeof item === 'object' && item !== null && 'title' in item) {
            const t = /** @type {{ title?: unknown }} */ (item).title;
            if (typeof t === 'string' && t.trim() !== '') {
                return `Go to ${t}`;
            }
        }

        return `Go to meal ${idx + 1}`;
    };

    /**
     * Align the track so the active logical card (middle ribbon copy, physical n…2n−1) is
     * horizontally centered in the gallery viewport. Uses screen-space card vs viewport centers.
     * When n===1: single slot at physical 0.
     *
     * @param {number} logicalIdx
     */
    const alignDesktopTrackToLogical = useCallback(
        (logicalIdx) => {
            const container = desktopGalleryRef.current;
            const track = desktopTrackRef.current;
            if (!container || !track) {
                return undefined;
            }
            const n = itemCount;
            const physical = n <= 1 ? 0 : n + logicalIdx;
            const card = desktopCardRefs.current[physical];
            if (!card) {
                return undefined;
            }
            const gr = container.getBoundingClientRect();
            const cw = gr.width;
            const tw = getDesktopTrackScrollExtent(track);

            const cur = desktopTrackX.get();
            const cr = card.getBoundingClientRect();
            const viewportCenterX = gr.left + cw / 2;
            const cardCenterX = cr.left + cr.width / 2;
            const deltaScreen = cardCenterX - viewportCenterX;
            let x = cur - deltaScreen;

            /** Entire ribbon fits: allow non-negative x only (nudge track right to center the active cell). */
            if (tw <= cw) {
                const maxX = cw - tw;

                return Math.max(0, Math.min(maxX, x));
            }

            /** Row overflows: negative x shifts track left; clamp so we do not expose empty past track bounds. */
            const minX = cw - tw;
            x = Math.max(minX, Math.min(0, x));

            if (tw + x < cw - 1) {
                x = minX;
            }

            return x;
        },
        [desktopTrackX, itemCount],
    );

    /** One logical step along the row (card width + gap); measured on the middle copy */
    const getDesktopStepPx = useCallback(() => {
        if (itemCount <= 1) {
            return 0;
        }
        const n = itemCount;
        const a = desktopCardRefs.current[n];
        const b = desktopCardRefs.current[n + 1];
        if (!a || !b) {
            return 0;
        }

        return b.offsetLeft - a.offsetLeft;
    }, [itemCount]);

    /**
     * Desktop arrows: tween ±step then snap with alignDesktopTrackToLogical(targetIndex) so track position matches dots.
     */
    const prevDesktop = useCallback(async () => {
        if (itemCount <= 1 || !isDesktopLayout) {
            return;
        }
        if (desktopNavBusyRef.current) {
            return;
        }
        desktopNavBusyRef.current = true;
        try {
            const n = itemCount;
            const from = desktopActiveIndexRef.current;
            const to = (from - 1 + n) % n;
            let stepPx = getDesktopStepPx();
            if (stepPx <= 0) {
                await new Promise((r) => requestAnimationFrame(r));
                stepPx = getDesktopStepPx();
            }
            if (stepPx <= 0) {
                return;
            }

            let cur = desktopTrackX.get();

            await animate(desktopTrackX, cur + stepPx, netflixSlideTransition()).finished;

            const tx = alignDesktopTrackToLogical(to);
            if (tx !== undefined) {
                desktopTrackX.set(tx);
            }

            setDesktopActiveIndex(to);
        } finally {
            desktopNavBusyRef.current = false;
        }
    }, [alignDesktopTrackToLogical, desktopTrackX, getDesktopStepPx, isDesktopLayout, itemCount]);

    const nextDesktop = useCallback(async () => {
        if (itemCount <= 1 || !isDesktopLayout) {
            return;
        }
        if (desktopNavBusyRef.current) {
            return;
        }
        desktopNavBusyRef.current = true;
        try {
            const n = itemCount;
            const from = desktopActiveIndexRef.current;
            const to = (from + 1) % n;
            let stepPx = getDesktopStepPx();
            if (stepPx <= 0) {
                await new Promise((r) => requestAnimationFrame(r));
                stepPx = getDesktopStepPx();
            }
            if (stepPx <= 0) {
                return;
            }

            let cur = desktopTrackX.get();

            await animate(desktopTrackX, cur - stepPx, netflixSlideTransition()).finished;

            const tx = alignDesktopTrackToLogical(to);
            if (tx !== undefined) {
                desktopTrackX.set(tx);
            }

            setDesktopActiveIndex(to);
        } finally {
            desktopNavBusyRef.current = false;
        }
    }, [alignDesktopTrackToLogical, desktopTrackX, getDesktopStepPx, isDesktopLayout, itemCount]);

    /** Dot jump: instant align (no index interpolation — avoids wrong-way tween) */
    const jumpDesktopToLogical = useCallback(
        (logicalIdx) => {
            if (itemCount <= 1 || !isDesktopLayout) {
                return;
            }
            if (desktopNavBusyRef.current) {
                return;
            }
            const bounded = ((logicalIdx % itemCount) + itemCount) % itemCount;

            const applyJump = () => {
                const tx = alignDesktopTrackToLogical(bounded);
                if (tx !== undefined) {
                    desktopTrackX.set(tx);
                    setDesktopActiveIndex(bounded);
                }
            };

            applyJump();
            requestAnimationFrame(() => {
                requestAnimationFrame(applyJump);
            });
        },
        [alignDesktopTrackToLogical, desktopTrackX, isDesktopLayout, itemCount],
    );

    useEffect(() => {
        if (!isDesktopLayout) {
            return;
        }
        const bump = () => setGalleryResizeSeq((s) => s + 1);
        const ro = new ResizeObserver(bump);

        const gallery = desktopGalleryRef.current;
        const track = desktopTrackRef.current;
        if (gallery) {
            ro.observe(gallery);
        }
        if (track) {
            ro.observe(track);
        }

        return () => {
            ro.disconnect();
        };
    }, [isDesktopLayout, itemCount]);

    /** Resize / desktop toggle: snap track to current index (no implicit wrap animation) */
    useLayoutEffect(() => {
        if (!isDesktopLayout || itemCount === 0) {
            return;
        }
        let innerId = 0;
        const outerId = requestAnimationFrame(() => {
            innerId = requestAnimationFrame(() => {
                const tx = alignDesktopTrackToLogical(desktopActiveIndexRef.current);
                if (tx !== undefined) {
                    desktopTrackX.set(tx);
                }
            });
        });

        return () => {
            cancelAnimationFrame(outerId);
            cancelAnimationFrame(innerId);
        };
    }, [
        alignDesktopTrackToLogical,
        desktopTrackX,
        galleryResizeSeq,
        isDesktopLayout,
        itemCount,
    ]);

    const useCarousel = itemCount > 2;

    if (!useCarousel && itemCount > 0) {
        const isPair = itemCount === 2;

        return (
            <div className="relative w-full">
                <div
                    className={[
                        'mx-auto flex w-full flex-row flex-nowrap items-stretch justify-center py-4',
                        isPair ? 'max-w-[min(100%,calc(302px*2+1.5rem))] gap-4 px-3 sm:gap-6 sm:px-6' : 'max-w-[302px] px-4',
                    ].join(' ')}
                >
                    {items.map((item, idx) => (
                        <div
                            key={`static-${getKey(item, idx)}`}
                            className={isPair ? STATIC_CARD_SHELL_PAIR : STATIC_CARD_SHELL_SINGLE}
                        >
                            {renderMealCard(item, idx, {
                                isFront: true,
                                stackPos: null,
                                deckLayout: 'ribbon',
                            })}
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <div className="group relative w-full md:px-16 lg:px-20">
            {/* Desktop ≥768px — triplicate ribbon + align snap; no 3D stack in DOM */}
            {isDesktopLayout && itemCount > 0 ? (
                <div className="relative w-full overflow-x-clip overflow-y-visible bg-[#F8F9F6] pb-2 pt-4 outline-none ring-0">
                    <div
                        ref={desktopGalleryRef}
                        className="relative min-h-[10rem] w-full min-w-0 bg-[#F8F9F6] px-8 py-6 outline-none ring-0 md:px-16 lg:px-20"
                    >
                        <div className="relative overflow-x-clip">
                        <motion.div
                            ref={desktopTrackRef}
                            style={{ x: desktopTrackX }}
                            className="relative z-0 flex w-max shrink-0 flex-nowrap items-stretch gap-6 will-change-transform transform-gpu"
                        >
                                {itemCount > 1 ? (
                                    Array.from({ length: DESKTOP_RIBBON_COPIES }, (_, copy) =>
                                        items.map((item, idx) => {
                                            const physicalIdx = copy * itemCount + idx;
                                            const isRibbonFocus = physicalIdx === itemCount + desktopActiveIndex;

                                            return (
                                                <div
                                                    key={`desktop-ribbon-${copy}-${getKey(item, idx)}`}
                                                    ref={(el) => {
                                                        desktopCardRefs.current[physicalIdx] = el;
                                                    }}
                                                    data-desktop-card=""
                                                    className={`${DESKTOP_CARD_SHELL} ${isRibbonFocus ? 'z-[5]' : 'z-0'}`}
                                                >
                                                    <motion.div
                                                        className={`flex min-h-0 flex-1 flex-col rounded-[12px] ${isRibbonFocus ? 'shadow-lg shadow-[#262A22]/12' : ''}`}
                                                        style={{ transformOrigin: 'center center' }}
                                                        animate={{
                                                            scale: isRibbonFocus ? 1.05 : 0.95,
                                                            opacity: isRibbonFocus ? 1 : 0.8,
                                                        }}
                                                        transition={netflixSlideTransition()}
                                                        whileHover={
                                                            isRibbonFocus
                                                                ? {
                                                                      scale: 1.08,
                                                                      transition: { duration: 0.2, ease: [0.22, 1, 0.36, 1] },
                                                                  }
                                                                : { scale: 0.96 }
                                                        }
                                                    >
                                                        {renderMealCard(item, idx, {
                                                            isFront: true,
                                                            stackPos: null,
                                                            deckLayout: 'ribbon',
                                                        })}
                                                    </motion.div>
                                                </div>
                                            );
                                        }),
                                    ).flat()
                                ) : (
                                    <div
                                        key={`desktop-single-${getKey(items[0], 0)}`}
                                        ref={(el) => {
                                            desktopCardRefs.current[0] = el;
                                        }}
                                        data-desktop-card=""
                                        className={`${DESKTOP_CARD_SHELL} z-[5]`}
                                    >
                                        <motion.div
                                            className="flex min-h-0 flex-1 flex-col rounded-[12px] shadow-lg shadow-[#262A22]/12"
                                            style={{ transformOrigin: 'center center' }}
                                            animate={{ scale: 1.05, opacity: 1 }}
                                            transition={netflixSlideTransition()}
                                            whileHover={{
                                                scale: 1.08,
                                                transition: { duration: 0.2, ease: [0.22, 1, 0.36, 1] },
                                            }}
                                        >
                                            {renderMealCard(items[0], 0, {
                                                isFront: true,
                                                stackPos: null,
                                                deckLayout: 'ribbon',
                                            })}
                                        </motion.div>
                                    </div>
                                )}
                        </motion.div>
                        </div>
                    </div>

                    {/* Rails pass clicks through except on the buttons (CRAFT / View Details stay reachable) */}
                    <div className="pointer-events-none absolute inset-y-0 left-0 z-[110] w-14 border-none bg-transparent shadow-none outline-none ring-0">
                        <button
                            type="button"
                            aria-label="Previous meal"
                            onClick={() => void prevDesktop()}
                            disabled={itemCount <= 1}
                            className="pointer-events-auto flex h-full min-h-[3rem] w-full items-center justify-center rounded-none border-0 bg-transparent text-[#262A22] shadow-none outline-none ring-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]/35 focus-visible:ring-offset-0 disabled:pointer-events-none disabled:opacity-30"
                        >
                            <svg className="h-9 w-9 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.25} d="M15 18l-6-6 6-6" />
                            </svg>
                        </button>
                    </div>
                    <div className="pointer-events-none absolute inset-y-0 right-0 z-[110] w-14 border-none bg-transparent shadow-none outline-none ring-0">
                        <button
                            type="button"
                            aria-label="Next meal"
                            onClick={() => void nextDesktop()}
                            disabled={itemCount <= 1}
                            className="pointer-events-auto flex h-full min-h-[3rem] w-full items-center justify-center rounded-none border-0 bg-transparent text-[#262A22] shadow-none outline-none ring-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]/35 focus-visible:ring-offset-0 disabled:pointer-events-none disabled:opacity-30"
                        >
                            <svg className="h-9 w-9 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.25} d="M9 18l6-6-6-6" />
                            </svg>
                        </button>
                    </div>
                </div>
            ) : null}

            {/* Mobile &lt;768px — compact row: chevrons ~10–14px from card edges (not screen edges) */}
            {!isDesktopLayout ? (
                <div className="flex w-full max-w-full items-center justify-center gap-2 px-1 pb-2 sm:gap-2.5 sm:px-2">
                    {itemCount === 0 ? null : (
                        <>
                            <button
                                type="button"
                                aria-label="Previous"
                                onClick={() => void nudgeThenThrow(-1)}
                                disabled={itemCount <= 1}
                                className="z-[110] flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-transparent text-[#262A22] transition-opacity hover:bg-black/[0.04] disabled:opacity-30"
                            >
                                <span className="text-3xl leading-none">‹</span>
                            </button>
                        <div className="relative z-[60] w-[min(270px,calc(100%-6.5rem))] max-w-full min-w-0 shrink-0 translate-x-0 transform-gpu">
                        <div
                            className="relative mx-auto w-full min-w-0 translate-x-0 transform-gpu"
                        >
                        {exitSession === null ? (
                            <>
                                {backLayerCount >= 1
                                    ? Array.from({ length: backLayerCount }, (_, i) => {
                                          const depth = backLayerCount - i;
                                          const mealIdx = modIndex(activeIndex + depth);

                                          return (
                                              <MobileDeckBackLayer
                                                  key={`idle-bg-d${depth}-${getKey(items[mealIdx], mealIdx)}`}
                                                  depth={depth}
                                                  xRaw={xRaw}
                                              >
                                                  {renderMealCard(items[mealIdx], mealIdx, {
                                                      isFront: false,
                                                      stackPos: depth <= 3 ? /** @type {1|2|3} */ (depth) : 3,
                                                      deckLayout: 'stack',
                                                  })}
                                              </MobileDeckBackLayer>
                                          );
                                      })
                                    : null}

                                {/* Active front */}
                                <motion.div
                                    className="relative w-full transform-gpu"
                                    style={{
                                        x: xRaw,
                                        rotate,
                                        scale: dragScale,
                                        zIndex: FRONT_SPEC.z,
                                        translateZ: 0,
                                    }}
                                    drag={itemCount > 1 ? 'x' : false}
                                    dragConstraints={{ left: 0, right: 0 }}
                                    dragElastic={0.1}
                                    onDragEnd={(_, info) => {
                                        if (itemCount <= 1) {
                                            return;
                                        }
                                        const v = info.velocity.x;
                                        const throwVelocity = 720;
                                        // Swipe left → exit left → prev; swipe right → exit right → next
                                        if (v <= -throwVelocity || info.offset.x <= -140) {
                                            void performReturnToBack(-1);
                                            return;
                                        }
                                        if (v >= throwVelocity || info.offset.x >= 140) {
                                            void performReturnToBack(1);
                                            return;
                                        }
                                        void animate(xRaw, 0, springSoft());
                                    }}
                                    transition={springSoft()}
                                >
                                    {renderMealCard(items[activeIndex], activeIndex, {
                                        isFront: true,
                                        stackPos: null,
                                        deckLayout: 'stack',
                                    })}
                                </motion.div>
                            </>
                        ) : (
                            <>
                                {/* During exit: static stack behind promoted (dir-specific indices) */}
                                {exitSession.dir === 1 ? (
                                    <>
                                        {backLayerCount >= 1
                                            ? Array.from({ length: backLayerCount }, (_, i) => {
                                                  const depth = backLayerCount - i;
                                                  const mealIdx = modIndex(exitSession.fromIndex + depth + 1);
                                                  const spec = getBackSpec(depth);

                                                  return (
                                                      <motion.div
                                                          key={`t-bg-d${depth}-${exitSession.fromIndex}-${getKey(items[mealIdx], mealIdx)}`}
                                                          aria-hidden="true"
                                                          className="pointer-events-none absolute left-0 top-0 w-full transform-gpu"
                                                          style={{
                                                              zIndex: spec.z,
                                                              x: spec.x,
                                                              scale: spec.scale,
                                                              filter: `blur(${spec.blur}px)`,
                                                              translateZ: 0,
                                                          }}
                                                          transition={springSoft()}
                                                      >
                                                          {renderMealCard(items[mealIdx], mealIdx, {
                                                              isFront: false,
                                                              stackPos: depth <= 3 ? /** @type {1|2|3} */ (depth) : 3,
                                                              deckLayout: 'stack',
                                                          })}
                                                      </motion.div>
                                                  );
                                              })
                                            : null}
                                    </>
                                ) : (
                                    (() => {
                                        const spec = getBackSpec(1);
                                        const mealIdx = modIndex(exitSession.fromIndex + 1);

                                        return (
                                            <motion.div
                                                key={`t-bg-rev-${exitSession.fromIndex}-${getKey(items[mealIdx], mealIdx)}`}
                                                aria-hidden="true"
                                                className="pointer-events-none absolute left-0 top-0 w-full transform-gpu"
                                                style={{
                                                    zIndex: spec.z,
                                                    x: spec.x,
                                                    scale: spec.scale,
                                                    filter: `blur(${spec.blur}px)`,
                                                    translateZ: 0,
                                                }}
                                                transition={springSoft()}
                                            >
                                                {renderMealCard(items[mealIdx], mealIdx, {
                                                    isFront: false,
                                                    stackPos: 2,
                                                    deckLayout: 'stack',
                                                })}
                                            </motion.div>
                                        );
                                    })()
                                )}

                                {/* Promoted: former middle (BG1) slides to x:0 / scale 1 / blur 0 as new hero */}
                                {promotedIndex != null ? (
                                    <motion.div
                                        key={`promoted-${getKey(items[promotedIndex], promotedIndex)}`}
                                        className="relative w-full transform-gpu"
                                        initial={{
                                            x: getBackSpec(1).x,
                                            scale: getBackSpec(1).scale,
                                            rotate: exitSession.dir === 1 ? -1.25 : 1.25,
                                            filter: `blur(${getBackSpec(1).blur}px)`,
                                            zIndex: 95,
                                        }}
                                        animate={{
                                            x: FRONT_SPEC.x,
                                            scale: FRONT_SPEC.scale,
                                            rotate: 0,
                                            filter: `blur(${FRONT_SPEC.blur}px)`,
                                            zIndex: FRONT_SPEC.z,
                                        }}
                                        transition={springSoft()}
                                        style={{ zIndex: 100, translateZ: 0 }}
                                    >
                                        {renderMealCard(items[promotedIndex], promotedIndex, {
                                            isFront: true,
                                            stackPos: null,
                                            deckLayout: 'stack',
                                        })}
                                    </motion.div>
                                ) : null}

                                {/* Exit clone flies off-screen then settles as back-of-stack */}
                                <motion.div
                                    key={`exit-${getKey(items[exitSession.fromIndex], exitSession.fromIndex)}`}
                                    aria-hidden="true"
                                    className="pointer-events-none absolute left-0 top-0 w-full transform-gpu"
                                    style={{
                                        x: exitX,
                                        scale: exitScale,
                                        filter: exitFilter,
                                        zIndex: exitZIndex,
                                        transformOrigin: 'center center',
                                        translateZ: 0,
                                    }}
                                    transition={springSoft()}
                                >
                                    {renderMealCard(items[exitSession.fromIndex], exitSession.fromIndex, {
                                        isFront: true,
                                        stackPos: null,
                                        deckLayout: 'stack',
                                    })}
                                </motion.div>
                            </>
                            )}
                        </div>
                        </div>
                            <button
                                type="button"
                                aria-label="Next"
                                onClick={() => void nudgeThenThrow(1)}
                                disabled={itemCount <= 1}
                                className="z-[110] flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-transparent text-[#262A22] transition-opacity hover:bg-black/[0.04] disabled:opacity-30"
                            >
                                <span className="text-3xl leading-none">›</span>
                            </button>
                        </>
                    )}
                </div>
            ) : null}

            {itemCount >= 1 ? (
                <div
                    className="flex w-full flex-col items-center justify-center px-2 pt-3 pb-1 sm:px-4"
                    role="tablist"
                    aria-label="Carousel pages"
                >
                    <div className="mx-auto flex w-full max-w-full flex-wrap items-center justify-center gap-2">
                        {items.map((item, idx) => {
                            const mobileHighlightIdx = mobileDotSwipeIdx ?? activeIndex;
                            const isActive = isDesktopLayout ? desktopActiveIndex === idx : mobileHighlightIdx === idx;

                            return (
                                <button
                                    key={`dot-${getKey(item, idx)}`}
                                    type="button"
                                    role="tab"
                                    aria-selected={isActive}
                                    aria-label={slideAriaLabel(item, idx)}
                                    disabled={!isDesktopLayout && exitSession !== null}
                                    onClick={() => {
                                        if (isDesktopLayout) {
                                            jumpDesktopToLogical(idx);

                                            return;
                                        }
                                        if (exitSession !== null || isThrowingRef.current) {
                                            return;
                                        }
                                        xRaw.set(0);
                                        setActiveIndex(idx);
                                    }}
                                    className={[
                                        'h-2 w-2 shrink-0 rounded-full transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]/40 focus-visible:ring-offset-2',
                                        isActive ? 'bg-[#5A6B44]' : 'bg-[#E0E0E0]',
                                        !isDesktopLayout && exitSession !== null ? 'pointer-events-none opacity-40' : '',
                                    ]
                                        .join(' ')
                                        .trim()}
                                />
                            );
                        })}
                    </div>
                </div>
            ) : null}
        </div>
    );
}
