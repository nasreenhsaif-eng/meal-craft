import { cloneElement, isValidElement, useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { animate, motion, useMotionValue, useTransform } from 'framer-motion';

/** @typedef {-1 | 1} DeckDirection */

const FRONT_SPEC = Object.freeze({
    z: 100,
    x: 0,
    scale: 1,
    blur: 0,
});

const BG1_SPEC = Object.freeze({
    z: 90,
    x: -25,
    scale: 0.92,
    blur: 2,
});

const BG2_SPEC = Object.freeze({
    z: 80,
    x: -50,
    scale: 0.85,
    blur: 4,
});

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

/** Shared shell for each desktop slide cell (matches gap-4 between cards) */
const DESKTOP_CARD_SHELL =
    'flex min-h-[340px] w-[240px] shrink-0 flex-col items-stretch sm:min-h-[360px] sm:w-[260px] lg:min-h-[380px] lg:w-[280px] transform-gpu';

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

    const dotsWrapRef = useRef(null);
    const dotRefs = useRef(/** @type {(HTMLButtonElement|null)[]} */ ([]));
    const [dotCenters, setDotCenters] = useState(/** @type {number[]} */ ([]));
    const isThrowingRef = useRef(false);

    /** Exit clone overlay */
    const exitX = useMotionValue(0);
    const exitScale = useMotionValue(FRONT_SPEC.scale);
    const exitBlur = useMotionValue(FRONT_SPEC.blur);
    /** z handled by style (number) animated via animate fallback */

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

    const targetIndexFromDir = (dir) =>
        dir === 1 ? modIndex(activeIndex + 1) : modIndex(activeIndex - 1);

    const pillBaseWidth = 32;

    // === Physics: front card drag ===
    const xRaw = useMotionValue(0);
    const rotate = useTransform(xRaw, [-220, 0, 220], [-8, 0, 8]);
    const dragScale = useTransform(xRaw, [-220, 0, 220], [0.995, 1, 0.995]);

    // Fan BG layers slightly when dragging (tighter stack → gentler fan)
    const bg1FanX = useTransform(xRaw, (latestX) => BG1_SPEC.x + latestX * 0.035);
    const bg2FanX = useTransform(xRaw, (latestX) => BG2_SPEC.x + latestX * 0.065);
    const bg1FanRotate = useTransform(xRaw, (latestX) => latestX * -0.008);
    const bg2FanRotate = useTransform(xRaw, (latestX) => latestX * -0.014);

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

        // 2) While off-screen: drop behind stack, match back-card look
        setExitZIndex(BACK_REJOIN_Z);
        exitScale.set(BG2_SPEC.scale);
        exitBlur.set(BG2_SPEC.blur);

        // 3) Drift into back-left fan slot (soft spring — not a hard snap)
        await animate(exitX, BG2_SPEC.x, springSoft()).finished;

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

    const paginationDotCount = itemCount;

    useEffect(() => {
        const wrap = dotsWrapRef.current;
        if (!wrap) {
            return;
        }

        const measure = () => {
            const n = paginationDotCount;
            const centers = dotRefs.current.slice(0, n).map((el) => {
                if (!el) {
                    return 0;
                }
                return el.offsetLeft + el.offsetWidth / 2;
            });
            setDotCenters(centers);
        };

        const raf = requestAnimationFrame(measure);
        window.addEventListener('resize', measure);
        return () => {
            cancelAnimationFrame(raf);
            window.removeEventListener('resize', measure);
        };
    }, [paginationDotCount, itemCount]);

    // === Worm indicator ===
    const activeDotLeft = useMemo(() => {
        const c = dotCenters[activeIndex] ?? 0;
        return c - pillBaseWidth / 2;
    }, [activeIndex, dotCenters]);

    const activeDotLeftDesktop = useMemo(() => {
        const c = dotCenters[desktopActiveIndex] ?? 0;
        return c - pillBaseWidth / 2;
    }, [desktopActiveIndex, dotCenters]);

    const wormLeft = useTransform(xRaw, (latestX) => {
        if (!dotCenters.length) {
            return activeDotLeft;
        }
        const intent = latestX < 0 ? -1 : latestX > 0 ? 1 : 0;
        if (intent === 0) {
            return activeDotLeft;
        }
        const targetIdx = targetIndexFromDir(intent);
        const targetCenter = dotCenters[targetIdx] ?? 0;
        const targetLeft = targetCenter - pillBaseWidth / 2;
        const progress = Math.min(1, Math.abs(latestX) / 140);
        return activeDotLeft + (targetLeft - activeDotLeft) * progress;
    });

    const wormWidth = useTransform(xRaw, (latestX) => {
        if (!dotCenters.length) {
            return pillBaseWidth;
        }
        const intent = latestX < 0 ? -1 : latestX > 0 ? 1 : 0;
        if (intent === 0) {
            return pillBaseWidth;
        }
        const targetIdx = targetIndexFromDir(intent);
        const currCenter = dotCenters[activeIndex] ?? 0;
        const targetCenter = dotCenters[targetIdx] ?? 0;
        const distance = Math.abs(targetCenter - currCenter);
        const progress = Math.min(1, Math.abs(latestX) / 140);
        return Math.min(pillBaseWidth + distance * progress, pillBaseWidth + 72);
    });

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
     * Align the track so logical index `logicalIdx` uses the middle ribbon copy only (physical n…2n−1).
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

            /**
             * When the row is shorter than the viewport, center it (positive translateX allowed).
             */
            if (tw <= cw) {
                return (cw - tw) / 2;
            }

            /**
             * Align using viewport geometry — `card.offsetLeft` is unreliable here because motion/flex
             * can change offsetParent chains; wrong `ideal` leaves a fixed void on the right at the last slides.
             */
            const minX = cw - tw;
            const cur = desktopTrackX.get();
            const cr = card.getBoundingClientRect();
            const deltaScreen = cr.left - gr.left;
            let x = cur - deltaScreen;

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

    return (
        <div className="group relative">
            {/* Mobile arrows: &lt; 768px — same 3D deck controls */}
            <button
                type="button"
                aria-label="Previous"
                onClick={() => void nudgeThenThrow(-1)}
                disabled={itemCount <= 1}
                className="absolute left-2 top-1/2 z-[115] flex h-10 w-10 -translate-y-1/2 items-center justify-center bg-transparent text-[#262A22] transition-opacity disabled:opacity-30 md:hidden"
            >
                <span className="text-3xl leading-none">‹</span>
            </button>
            <button
                type="button"
                aria-label="Next"
                onClick={() => void nudgeThenThrow(1)}
                disabled={itemCount <= 1}
                className="absolute right-2 top-1/2 z-[115] flex h-10 w-10 -translate-y-1/2 items-center justify-center bg-transparent text-[#262A22] transition-opacity disabled:opacity-30 md:hidden"
            >
                <span className="text-3xl leading-none">›</span>
            </button>

            {/* Desktop ≥768px — triplicate ribbon + align snap; no 3D stack in DOM */}
            {isDesktopLayout && itemCount > 0 ? (
                <div className="relative w-full overflow-x-clip overflow-y-visible bg-[#F8F9F6] pb-2 pt-4 outline-none ring-0">
                    <div
                        ref={desktopGalleryRef}
                        className="relative min-h-[10rem] w-full min-w-0 bg-[#F8F9F6] py-6 outline-none ring-0"
                    >
                        <div className="relative overflow-x-clip">
                        <motion.div
                            ref={desktopTrackRef}
                            style={{ x: desktopTrackX }}
                            className="relative z-0 flex w-max shrink-0 flex-nowrap items-stretch gap-4 will-change-transform transform-gpu"
                        >
                                {itemCount > 1 ? (
                                    Array.from({ length: DESKTOP_RIBBON_COPIES }, (_, copy) =>
                                        items.map((item, idx) => (
                                            <motion.div
                                                key={`desktop-ribbon-${copy}-${getKey(item, idx)}`}
                                                ref={(el) => {
                                                    desktopCardRefs.current[copy * itemCount + idx] = el;
                                                }}
                                                data-desktop-card=""
                                                className={DESKTOP_CARD_SHELL}
                                                style={{ transformOrigin: 'center center' }}
                                                whileHover={{
                                                    scale: 1.05,
                                                    transition: { duration: 0.2, ease: [0.22, 1, 0.36, 1] },
                                                }}
                                            >
                                                <div className="flex min-h-0 flex-1 flex-col">
                                                    {renderMealCard(item, idx, {
                                                        isFront: true,
                                                        stackPos: null,
                                                        deckLayout: 'ribbon',
                                                    })}
                                                </div>
                                            </motion.div>
                                        )),
                                    ).flat()
                                ) : (
                                    <motion.div
                                        key={`desktop-single-${getKey(items[0], 0)}`}
                                        ref={(el) => {
                                            desktopCardRefs.current[0] = el;
                                        }}
                                        data-desktop-card=""
                                        className={DESKTOP_CARD_SHELL}
                                        style={{ transformOrigin: 'center center' }}
                                        whileHover={{
                                            scale: 1.05,
                                            transition: { duration: 0.2, ease: [0.22, 1, 0.36, 1] },
                                        }}
                                    >
                                        <div className="flex min-h-0 flex-1 flex-col">
                                            {renderMealCard(items[0], 0, {
                                                isFront: true,
                                                stackPos: null,
                                                deckLayout: 'ribbon',
                                            })}
                                        </div>
                                    </motion.div>
                                )}
                        </motion.div>
                        </div>
                    </div>

                    {/* Rails pass clicks through except on the buttons (CRAFT / View Details stay reachable) */}
                    <div className="pointer-events-none absolute inset-y-0 left-0 z-[100] w-14 border-none bg-transparent shadow-none outline-none ring-0">
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
                    <div className="pointer-events-none absolute inset-y-0 right-0 z-[100] w-14 border-none bg-transparent shadow-none outline-none ring-0">
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

            {/* Mobile &lt;768px — 3D stacked deck */}
            {!isDesktopLayout ? (
                <div className="relative flex items-center justify-center pb-2">
                    {itemCount === 0 ? null : (
                        <div className="relative w-[min(90vw,440px)] max-w-full" style={{ zIndex: 60 }}>
                        {exitSession === null ? (
                            <>
                                {/* BG2 farthest */}
                                <motion.div
                                    key={`bg2-${getKey(items[modIndex(activeIndex + 2)], modIndex(activeIndex + 2))}`}
                                    aria-hidden="true"
                                    className="pointer-events-none absolute left-0 top-0 transform-gpu"
                                    style={{
                                        zIndex: BG2_SPEC.z,
                                        x: bg2FanX,
                                        rotate: bg2FanRotate,
                                        scale: BG2_SPEC.scale,
                                        filter: `blur(${BG2_SPEC.blur}px)`,
                                        translateZ: 0,
                                    }}
                                    transition={springSoft()}
                                >
                                    {renderMealCard(items[modIndex(activeIndex + 2)], modIndex(activeIndex + 2), {
                                        isFront: false,
                                        stackPos: 2,
                                        deckLayout: 'stack',
                                    })}
                                </motion.div>
                                {/* BG1 */}
                                <motion.div
                                    key={`bg1-${getKey(items[modIndex(activeIndex + 1)], modIndex(activeIndex + 1))}`}
                                    aria-hidden="true"
                                    className="pointer-events-none absolute left-0 top-0 transform-gpu"
                                    style={{
                                        zIndex: BG1_SPEC.z,
                                        x: bg1FanX,
                                        rotate: bg1FanRotate,
                                        scale: BG1_SPEC.scale,
                                        filter: `blur(${BG1_SPEC.blur}px)`,
                                        translateZ: 0,
                                    }}
                                    transition={springSoft()}
                                >
                                    {renderMealCard(items[modIndex(activeIndex + 1)], modIndex(activeIndex + 1), {
                                        isFront: false,
                                        stackPos: 1,
                                        deckLayout: 'stack',
                                    })}
                                </motion.div>

                                {/* Active front */}
                                <motion.div
                                    className="relative transform-gpu"
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
                                        <motion.div
                                            key={`t-bg2-${exitSession.fromIndex}-${getKey(items[modIndex(exitSession.fromIndex + 3)], modIndex(exitSession.fromIndex + 3))}`}
                                            aria-hidden="true"
                                            className="pointer-events-none absolute left-0 top-0 transform-gpu"
                                            style={{
                                                zIndex: BG2_SPEC.z,
                                                x: BG2_SPEC.x,
                                                scale: BG2_SPEC.scale,
                                                filter: `blur(${BG2_SPEC.blur}px)`,
                                                translateZ: 0,
                                            }}
                                            transition={springSoft()}
                                        >
                                            {renderMealCard(items[modIndex(exitSession.fromIndex + 3)], modIndex(exitSession.fromIndex + 3), {
                                                isFront: false,
                                                stackPos: 2,
                                                deckLayout: 'stack',
                                            })}
                                        </motion.div>
                                        <motion.div
                                            key={`t-bg1-${exitSession.fromIndex}-${getKey(items[modIndex(exitSession.fromIndex + 2)], modIndex(exitSession.fromIndex + 2))}`}
                                            aria-hidden="true"
                                            className="pointer-events-none absolute left-0 top-0 transform-gpu"
                                            style={{
                                                zIndex: BG1_SPEC.z,
                                                x: BG1_SPEC.x,
                                                scale: BG1_SPEC.scale,
                                                filter: `blur(${BG1_SPEC.blur}px)`,
                                                translateZ: 0,
                                            }}
                                            transition={springSoft()}
                                        >
                                            {renderMealCard(items[modIndex(exitSession.fromIndex + 2)], modIndex(exitSession.fromIndex + 2), {
                                                isFront: false,
                                                stackPos: 1,
                                                deckLayout: 'stack',
                                            })}
                                        </motion.div>
                                    </>
                                ) : (
                                    <motion.div
                                        key={`t-bg2-rev-${exitSession.fromIndex}-${getKey(items[modIndex(exitSession.fromIndex + 1)], modIndex(exitSession.fromIndex + 1))}`}
                                        aria-hidden="true"
                                        className="pointer-events-none absolute left-0 top-0 transform-gpu"
                                        style={{
                                            zIndex: BG2_SPEC.z,
                                            x: BG2_SPEC.x,
                                            scale: BG2_SPEC.scale,
                                            filter: `blur(${BG2_SPEC.blur}px)`,
                                            translateZ: 0,
                                        }}
                                        transition={springSoft()}
                                    >
                                        {renderMealCard(items[modIndex(exitSession.fromIndex + 1)], modIndex(exitSession.fromIndex + 1), {
                                            isFront: false,
                                            stackPos: 2,
                                            deckLayout: 'stack',
                                        })}
                                    </motion.div>
                                )}

                                {/* Promoted: former middle (BG1) slides to x:0 / scale 1 / blur 0 as new hero */}
                                {promotedIndex != null ? (
                                    <motion.div
                                        key={`promoted-${getKey(items[promotedIndex], promotedIndex)}`}
                                        className="relative transform-gpu"
                                        initial={{
                                            x: BG1_SPEC.x,
                                            scale: BG1_SPEC.scale,
                                            rotate: exitSession.dir === 1 ? -1.25 : 1.25,
                                            filter: `blur(${BG1_SPEC.blur}px)`,
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
                                    className="pointer-events-none absolute left-0 top-0 transform-gpu"
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
                    )}
                </div>
            ) : null}

            {itemCount > 1 ? (
                <div className="flex w-full justify-center px-4 pt-6 pb-8 sm:px-6 lg:px-8">
                    <div className="relative">
                        <div ref={dotsWrapRef} className="relative flex items-center gap-2">
                            {items.map((item, idx) => (
                                <button
                                    key={`dot-${getKey(item, idx)}`}
                                    type="button"
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
                                    ref={(el) => {
                                        dotRefs.current[idx] = el;
                                    }}
                                    className="relative h-2 w-2 rounded-full bg-[#E0E0E0] disabled:pointer-events-none disabled:opacity-40"
                                />
                            ))}

                            {isDesktopLayout ? (
                                <motion.div
                                    layoutId={deckScopeKey ? `mc-deck-worm-desktop-${deckScopeKey}` : 'mc-deck-worm-desktop'}
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-0 h-2 rounded-full bg-[#5A6B44] transform-gpu"
                                    animate={{ left: activeDotLeftDesktop }}
                                    transition={netflixSlideTransition()}
                                    style={{ width: pillBaseWidth, translateZ: 0 }}
                                />
                            ) : (
                                <motion.div
                                    layoutId={deckScopeKey ? `mc-deck-worm-mobile-${deckScopeKey}` : 'mc-deck-worm-mobile'}
                                    aria-hidden="true"
                                    className="pointer-events-none absolute top-0 h-2 rounded-full bg-[#5A6B44] transform-gpu"
                                    style={{ left: wormLeft, width: wormWidth, translateZ: 0 }}
                                    transition={springSoft()}
                                />
                            )}
                        </div>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
