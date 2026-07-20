import { cloneElement, isValidElement, useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { animate, motion, useMotionValue } from 'framer-motion';

/** Triplicate ribbon for seamless wrap in both directions. */
const RIBBON_COPIES = 3;

/** Overlaid nav buttons — track peeks underneath. */
const RIBBON_ARROW_ZONE_CLASS = 'w-10 lg:w-11';

/** Gradual edge blur strips — wider than arrow zone so peek softens into center cards. */
const RIBBON_EDGE_BLUR_WIDTH_CLASS = 'w-12 md:w-14 lg:w-16';

/**
 * Ribbon slide — gentle start (aggressive ease-out felt like a jump on arrow click).
 * Edge blur + dots carry focus; card chrome stays uniform so index changes don't pop.
 */
function ribbonSlideTransition() {
    return { type: 'tween', duration: 0.42, ease: [0.4, 0, 0.2, 1] };
}

/** Ribbon slide cell — consistent width on mobile and desktop */
const RIBBON_CARD_SHELL =
    'flex min-h-[232px] w-[min(280px,calc(100vw-5.5rem))] shrink-0 flex-col items-stretch sm:min-h-[248px] md:w-[286px] lg:min-h-[264px] lg:w-[302px] transform-gpu';

const STATIC_CARD_SHELL_SINGLE =
    'flex min-h-[232px] w-full max-w-[302px] shrink-0 flex-col items-stretch sm:min-h-[248px] sm:w-[286px] lg:min-h-[264px] lg:w-[302px] transform-gpu';

/** Desktop side-by-side pair — fixed equal width; stretch to matched height. */
const STATIC_PAIR_CARD_SHELL =
    'flex h-full w-full min-w-0 max-w-[302px] flex-1 flex-col md:w-[302px] md:flex-none transform-gpu';

/** Desktop side-by-side triple — equal flex columns within a 960px row. */
const STATIC_TRIPLE_CARD_SHELL =
    'flex h-full w-full min-w-0 max-w-[302px] flex-1 flex-col transform-gpu';

const DESKTOP_MIN_WIDTH_PX = 768;

/**
 * @param {number} minWidthPx
 */
function useMinWidth(minWidthPx) {
    const [matches, setMatches] = useState(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return window.matchMedia(`(min-width: ${minWidthPx}px)`).matches;
    });

    useEffect(() => {
        const mq = window.matchMedia(`(min-width: ${minWidthPx}px)`);
        const onChange = () => setMatches(mq.matches);
        onChange();
        mq.addEventListener('change', onChange);

        return () => mq.removeEventListener('change', onChange);
    }, [minWidthPx]);

    return matches;
}

/**
 * @param {number} itemCount
 */
function ribbonStageMaxWidthClass(itemCount) {
    if (itemCount >= 6) {
        return 'w-full max-w-[1480px]';
    }

    if (itemCount >= 4) {
        return 'w-full max-w-[1040px]';
    }

    if (itemCount === 3) {
        return 'w-full max-w-[820px]';
    }

    return 'w-full max-w-full';
}

/**
 * Two-option decks use a single track (no duplicate peeks). Three+ meals triplicate for infinite wrap.
 *
 * @param {number} itemCount
 */
function ribbonCopyCount(itemCount) {
    if (itemCount <= 1) {
        return 1;
    }

    if (itemCount === 2) {
        return 1;
    }

    return RIBBON_COPIES;
}

/**
 * @param {number} logicalIdx
 * @param {number} itemCount
 * @param {number} copies
 */
function focusedPhysicalIndex(logicalIdx, itemCount, copies) {
    if (itemCount <= 1) {
        return 0;
    }

    if (copies === 1) {
        return logicalIdx;
    }

    return itemCount + logicalIdx;
}

/**
 * Target track `x` to center a logical card.
 * Uses offsetLeft (same space as step slides) so a post-slide snap cannot hitch from getBoundingClientRect drift.
 *
 * @param {number} logicalIdx
 * @param {HTMLDivElement} container
 * @param {HTMLDivElement} track
 * @param {(HTMLDivElement|null)[]} cards
 * @param {number} itemCount
 * @param {number} copies
 */
function trackXForCenteredLogical(logicalIdx, container, track, cards, itemCount, copies) {
    const physical = focusedPhysicalIndex(logicalIdx, itemCount, copies);
    const card = cards[physical];
    if (!card) {
        return undefined;
    }

    const cw = container.clientWidth;
    if (cw <= 0) {
        return undefined;
    }

    const cardCenter = card.offsetLeft + card.offsetWidth / 2;
    let x = cw / 2 - cardCenter;

    // Finite ribbons may need edge clamping; infinite triplicate must not — clamp caused end-of-slide jumps.
    if (copies < RIBBON_COPIES) {
        const tw = Math.max(track.scrollWidth, track.offsetWidth);
        if (tw > cw) {
            const minX = cw - tw;
            x = Math.max(minX, Math.min(0, x));
        }
    }

    return x;
}

/**
 * Horizontal ribbon carousel — mobile peek ribbon; desktop uses a static pair for two-option decks.
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
 * @param {(item: T, index: number, ctx: { isFront: boolean, stackPos: (1|2|3|null), deckLayout: 'ribbon'|'stack'|'staticPair' }) => import('react').ReactNode} props.renderCard
 */
export default function StackedDeckCarousel({ title: _title, items: itemsProp, meals, getKey, renderCard, deckScopeKey }) {
    const items = meals ?? itemsProp ?? [];
    const itemCount = items.length;

    const deckScopePrevRef = useRef(/** @type {string|undefined} */ (undefined));

    /** Bumps when gallery viewport size changes so the slide track recenters */
    const [galleryResizeSeq, setGalleryResizeSeq] = useState(0);

    const [ribbonActiveIndex, setRibbonActiveIndex] = useState(0);

    const copies = ribbonCopyCount(itemCount);
    const isDesktop = useMinWidth(DESKTOP_MIN_WIDTH_PX);
    const useDesktopStaticPair = itemCount === 2 && isDesktop;
    const useDesktopStaticTriple = itemCount === 3 && isDesktop;

    const galleryRef = useRef(/** @type {HTMLDivElement|null} */ (null));
    const trackRef = useRef(/** @type {HTMLDivElement|null} */ (null));
    const cardRefs = useRef(/** @type {(HTMLDivElement|null)[]} */ ([]));
    const trackX = useMotionValue(0);
    const ribbonActiveIndexRef = useRef(0);
    const navBusyRef = useRef(false);

    useEffect(() => {
        ribbonActiveIndexRef.current = ribbonActiveIndex;
    }, [ribbonActiveIndex]);

    useEffect(() => {
        if (itemCount <= 1) {
            setRibbonActiveIndex(0);
            return;
        }
        setRibbonActiveIndex((i) => Math.min(i, itemCount - 1));
    }, [itemCount]);

    useEffect(() => {
        if (deckScopeKey === undefined) {
            deckScopePrevRef.current = undefined;

            return;
        }
        if (deckScopePrevRef.current === deckScopeKey) {
            return;
        }
        deckScopePrevRef.current = deckScopeKey;
        setRibbonActiveIndex(0);
        trackX.set(0);
    }, [deckScopeKey, trackX]);

    /**
     * Parent owns selection; clone only to guarantee handlers exist for breakpoint toggles.
     *
     * @param {T} item
     * @param {number} idx
     * @param {{ isFront: boolean, stackPos: (1|2|3|null), deckLayout: 'ribbon'|'stack'|'staticPair' }} ctx
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
     * Align the track so the active logical card is horizontally centered in the gallery viewport.
     *
     * @param {number} logicalIdx
     */
    const alignTrackToLogical = useCallback(
        (logicalIdx) => {
            const container = galleryRef.current;
            const track = trackRef.current;
            if (!container || !track || itemCount === 0) {
                return undefined;
            }

            return trackXForCenteredLogical(
                logicalIdx,
                container,
                track,
                cardRefs.current,
                itemCount,
                copies,
            );
        },
        [copies, itemCount],
    );

    const usesInfiniteRibbon = copies === RIBBON_COPIES;

    /**
     * Signed horizontal distance between two logical slides (uses adjacent copy when wrapping).
     *
     * @param {number} from
     * @param {number} to
     */
    const getStepBetweenLogical = useCallback(
        (from, to) => {
            if (itemCount <= 1) {
                return 0;
            }
            const n = itemCount;
            let physicalFrom = focusedPhysicalIndex(from, n, copies);
            let physicalTo = focusedPhysicalIndex(to, n, copies);

            if (usesInfiniteRibbon) {
                if (from === n - 1 && to === 0) {
                    physicalTo = 2 * n;
                } else if (from === 0 && to === n - 1) {
                    physicalTo = n - 1;
                }
            }

            const a = cardRefs.current[physicalFrom];
            const b = cardRefs.current[physicalTo];
            if (!a || !b) {
                return 0;
            }

            return b.offsetLeft - a.offsetLeft;
        },
        [copies, itemCount, usesInfiniteRibbon],
    );

    /**
     * Snap track onto the centered target without a second visible tween (that felt like a jump after the slide).
     *
     * @param {number} logicalIdx
     * @param {{ force?: boolean }} [options]
     */
    const snapTrackToLogical = useCallback(
        (logicalIdx, { force = false } = {}) => {
            const tx = alignTrackToLogical(logicalIdx);
            if (tx === undefined) {
                return;
            }

            const drift = Math.abs(trackX.get() - tx);
            if (!force && drift <= 2) {
                return;
            }

            trackX.set(tx);
        },
        [alignTrackToLogical, trackX],
    );

    /**
     * After sliding onto a clone at the loop seam, instantly re-home to the matching middle-copy card
     * without a visible jump (offsetLeft delta keeps the same pixels on screen).
     *
     * @param {number} logicalIdx
     * @param {number} physicalShown
     */
    const rehomeInfiniteRibbonToMiddle = useCallback(
        (logicalIdx, physicalShown) => {
            const physicalMiddle = focusedPhysicalIndex(logicalIdx, itemCount, copies);
            const middle = cardRefs.current[physicalMiddle];
            const shown = cardRefs.current[physicalShown];
            if (!middle || !shown) {
                snapTrackToLogical(logicalIdx, { force: true });

                return;
            }

            trackX.set(trackX.get() + (shown.offsetLeft - middle.offsetLeft));
        },
        [copies, itemCount, snapTrackToLogical, trackX],
    );

    /**
     * @param {1 | -1} direction
     */
    const moveRibbon = useCallback(
        async (direction) => {
            if (itemCount <= 1) {
                return;
            }
            if (navBusyRef.current) {
                return;
            }
            navBusyRef.current = true;
            try {
                const n = itemCount;
                const from = ribbonActiveIndexRef.current;
                const to = direction === 1 ? (from + 1) % n : (from - 1 + n) % n;
                const wraps =
                    usesInfiniteRibbon &&
                    ((from === n - 1 && to === 0) || (from === 0 && to === n - 1));

                let stepPx = getStepBetweenLogical(from, to);
                if (stepPx === 0) {
                    await new Promise((r) => requestAnimationFrame(r));
                    stepPx = getStepBetweenLogical(from, to);
                }
                if (stepPx === 0) {
                    return;
                }

                const cur = trackX.get();
                await animate(trackX, cur - stepPx, ribbonSlideTransition()).finished;

                // Adjacent steps already land on the correct pixel; a post-slide snap hitch was the jump.
                if (wraps) {
                    const physicalShown = to === 0 ? 2 * n : n - 1;
                    rehomeInfiniteRibbonToMiddle(to, physicalShown);
                }

                // Update active index only after the slide — never before (focus chrome pop felt like a jump).
                setRibbonActiveIndex(to);
                ribbonActiveIndexRef.current = to;

                // Hold busy one frame so resize/align layout work cannot fight the settle.
                await new Promise((r) => requestAnimationFrame(r));
            } finally {
                navBusyRef.current = false;
            }
        },
        [
            getStepBetweenLogical,
            itemCount,
            rehomeInfiniteRibbonToMiddle,
            trackX,
            usesInfiniteRibbon,
        ],
    );

    const prevRibbon = useCallback(() => moveRibbon(-1), [moveRibbon]);

    const nextRibbon = useCallback(() => moveRibbon(1), [moveRibbon]);

    const jumpToLogical = useCallback(
        async (logicalIdx) => {
            if (itemCount <= 1) {
                return;
            }
            if (navBusyRef.current) {
                return;
            }
            const bounded = ((logicalIdx % itemCount) + itemCount) % itemCount;
            if (bounded === ribbonActiveIndexRef.current) {
                return;
            }

            navBusyRef.current = true;
            try {
                const from = ribbonActiveIndexRef.current;
                const n = itemCount;
                const wraps =
                    usesInfiniteRibbon &&
                    ((from === n - 1 && bounded === 0) || (from === 0 && bounded === n - 1));

                let stepPx = getStepBetweenLogical(from, bounded);
                if (stepPx === 0) {
                    await new Promise((r) => requestAnimationFrame(r));
                    stepPx = getStepBetweenLogical(from, bounded);
                }

                if (stepPx !== 0) {
                    const cur = trackX.get();
                    await animate(trackX, cur - stepPx, ribbonSlideTransition()).finished;
                }

                if (wraps) {
                    const physicalShown = bounded === 0 ? 2 * n : n - 1;
                    rehomeInfiniteRibbonToMiddle(bounded, physicalShown);
                } else if (stepPx === 0) {
                    // Refs not ready — snap once; never snap after a successful adjacent/multi-step slide.
                    snapTrackToLogical(bounded, { force: true });
                }

                setRibbonActiveIndex(bounded);
                ribbonActiveIndexRef.current = bounded;

                await new Promise((r) => requestAnimationFrame(r));
            } finally {
                navBusyRef.current = false;
            }
        },
        [
            getStepBetweenLogical,
            itemCount,
            rehomeInfiniteRibbonToMiddle,
            snapTrackToLogical,
            trackX,
            usesInfiniteRibbon,
        ],
    );

    useEffect(() => {
        const gallery = galleryRef.current;
        if (!gallery) {
            return undefined;
        }

        let lastWidth = gallery.getBoundingClientRect().width;
        const ro = new ResizeObserver((entries) => {
            for (const entry of entries) {
                const nextWidth = entry.contentRect.width;
                if (Math.abs(nextWidth - lastWidth) <= 1) {
                    continue;
                }
                lastWidth = nextWidth;
                setGalleryResizeSeq((s) => s + 1);
            }
        });

        ro.observe(gallery);

        return () => {
            ro.disconnect();
        };
    }, [itemCount]);

    useLayoutEffect(() => {
        if (itemCount === 0 || useDesktopStaticPair || useDesktopStaticTriple || navBusyRef.current) {
            return;
        }
        let innerId = 0;
        const outerId = requestAnimationFrame(() => {
            innerId = requestAnimationFrame(() => {
                if (navBusyRef.current) {
                    return;
                }

                const tx = alignTrackToLogical(ribbonActiveIndexRef.current);
                if (tx === undefined) {
                    return;
                }

                const drift = Math.abs(trackX.get() - tx);
                // Ignore sub-pixel / font reflow drift — recentering mid-session felt like a jump.
                if (drift <= 8) {
                    return;
                }

                trackX.set(tx);
            });
        });

        return () => {
            cancelAnimationFrame(outerId);
            cancelAnimationFrame(innerId);
        };
    }, [alignTrackToLogical, galleryResizeSeq, itemCount, copies, trackX, useDesktopStaticPair, useDesktopStaticTriple]);

    if (itemCount === 1) {
        return (
            <div className="relative w-full px-4 py-4">
                <div className="mx-auto max-w-[302px]">
                    <div className={STATIC_CARD_SHELL_SINGLE}>
                        {renderMealCard(items[0], 0, {
                            isFront: true,
                            stackPos: null,
                            deckLayout: 'ribbon',
                        })}
                    </div>
                </div>
            </div>
        );
    }

    if (useDesktopStaticPair) {
        return (
            <div className="w-full px-4 py-4">
                <div className="mx-auto flex w-full max-w-[680px] items-stretch justify-center gap-4 md:gap-6">
                    {items.map((item, idx) => (
                        <div key={`static-pair-${getKey(item, idx)}`} className={STATIC_PAIR_CARD_SHELL}>
                            {renderMealCard(item, idx, {
                                isFront: true,
                                stackPos: null,
                                deckLayout: 'staticPair',
                            })}
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (useDesktopStaticTriple) {
        return (
            <div className="w-full px-4 py-4">
                <div className="mx-auto flex w-full max-w-[960px] items-stretch justify-center gap-3 md:gap-4 lg:gap-6">
                    {items.map((item, idx) => (
                        <div key={`static-triple-${getKey(item, idx)}`} className={STATIC_TRIPLE_CARD_SHELL}>
                            {renderMealCard(item, idx, {
                                isFront: true,
                                stackPos: null,
                                deckLayout: 'staticPair',
                            })}
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    const stageMaxW = ribbonStageMaxWidthClass(itemCount);

    const ribbonArrowButtonClass =
        'pointer-events-auto flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-0 bg-white/90 text-[#262A22] shadow-sm shadow-[#262A22]/10 outline-none ring-0 backdrop-blur-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]/35 focus-visible:ring-offset-0 disabled:pointer-events-none disabled:opacity-30';

    const ribbonEdgeBlurClass = [
        'pointer-events-none absolute inset-y-4 z-[100]',
        RIBBON_EDGE_BLUR_WIDTH_CLASS,
        'bg-white/20 backdrop-blur-[6px] md:backdrop-blur-[8px]',
    ].join(' ');

    return (
        <div className="group relative w-full">
            <div className="relative w-full overflow-y-visible pb-2 pt-4 outline-none ring-0">
                <div className={`relative mx-auto min-w-0 ${stageMaxW}`}>
                    <div
                        ref={galleryRef}
                        className="relative min-h-[10rem] w-full min-w-0 overflow-x-clip px-1 py-4 outline-none ring-0 md:px-2 md:py-5"
                    >
                        <motion.div
                            ref={trackRef}
                            style={{ x: trackX }}
                            className="relative z-0 flex w-max shrink-0 flex-nowrap items-stretch gap-3 will-change-transform transform-gpu md:gap-6"
                        >
                            {Array.from({ length: copies }, (_, copy) =>
                                items.map((item, idx) => {
                                    const physicalIdx = copy * itemCount + idx;
                                    const focusedPhysical = focusedPhysicalIndex(
                                        ribbonActiveIndex,
                                        itemCount,
                                        copies,
                                    );
                                    const isNearFocus = Math.abs(physicalIdx - focusedPhysical) <= 1;

                                    return (
                                        <div
                                            key={`ribbon-${copy}-${getKey(item, idx)}`}
                                            ref={(el) => {
                                                cardRefs.current[physicalIdx] = el;
                                            }}
                                            data-ribbon-card=""
                                            className={RIBBON_CARD_SHELL}
                                        >
                                            <div className="flex min-h-0 flex-1 flex-col rounded-[12px]">
                                                {renderMealCard(item, idx, {
                                                    isFront: isNearFocus,
                                                    stackPos: null,
                                                    deckLayout: 'ribbon',
                                                })}
                                            </div>
                                        </div>
                                    );
                                }),
                            ).flat()}
                        </motion.div>
                    </div>

                    <div
                        className={`${ribbonEdgeBlurClass} left-0 [mask-image:linear-gradient(to_right,black_0%,transparent_100%)] [-webkit-mask-image:linear-gradient(to_right,black_0%,transparent_100%)]`}
                        aria-hidden="true"
                    />
                    <div
                        className={`${ribbonEdgeBlurClass} right-0 [mask-image:linear-gradient(to_left,black_0%,transparent_100%)] [-webkit-mask-image:linear-gradient(to_left,black_0%,transparent_100%)]`}
                        aria-hidden="true"
                    />

                    <div
                        className={`pointer-events-none absolute inset-y-0 left-0 z-[110] flex ${RIBBON_ARROW_ZONE_CLASS} items-center justify-center`}
                    >
                        <button
                            type="button"
                            aria-label="Previous meal"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => void prevRibbon()}
                            disabled={itemCount <= 1}
                            className={ribbonArrowButtonClass}
                        >
                            <svg
                                className="h-7 w-7 shrink-0 md:h-8 md:w-8"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.25} d="M15 18l-6-6 6-6" />
                            </svg>
                        </button>
                    </div>
                    <div
                        className={`pointer-events-none absolute inset-y-0 right-0 z-[110] flex ${RIBBON_ARROW_ZONE_CLASS} items-center justify-center`}
                    >
                        <button
                            type="button"
                            aria-label="Next meal"
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => void nextRibbon()}
                            disabled={itemCount <= 1}
                            className={ribbonArrowButtonClass}
                        >
                            <svg
                                className="h-7 w-7 shrink-0 md:h-8 md:w-8"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.25} d="M9 18l6-6-6-6" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div
                className="flex w-full flex-col items-center justify-center px-4 pt-3 pb-1"
                role="tablist"
                aria-label="Carousel pages"
            >
                <div className="mx-auto flex w-full max-w-full flex-wrap items-center justify-center gap-2">
                    {items.map((item, idx) => {
                        const isActive = ribbonActiveIndex === idx;

                        return (
                            <button
                                key={`dot-${getKey(item, idx)}`}
                                type="button"
                                role="tab"
                                aria-selected={isActive}
                                aria-label={slideAriaLabel(item, idx)}
                                onClick={() => jumpToLogical(idx)}
                                className={[
                                    'h-2 w-2 shrink-0 rounded-full transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]/40 focus-visible:ring-offset-2',
                                    isActive ? 'bg-[#5A6B44]' : 'bg-[#E0E0E0]',
                                ]
                                    .join(' ')
                                    .trim()}
                            />
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
