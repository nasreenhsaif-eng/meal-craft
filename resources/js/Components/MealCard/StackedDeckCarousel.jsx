import { cloneElement, isValidElement, useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { animate, motion, useMotionValue } from 'framer-motion';

/** Triplicate ribbon for seamless wrap in both directions. */
const RIBBON_COPIES = 3;

/** Focus vs neighbor scale — subtle so the row feels even. */
const RIBBON_FOCUS_SCALE = 1.02;
const RIBBON_NEIGHBOR_SCALE = 0.98;
const RIBBON_FOCUS_OPACITY = 1;
const RIBBON_NEIGHBOR_OPACITY = 0.88;

/** Desktop row slide — direction follows arrow, not index wrap */
function netflixSlideTransition() {
    return { type: 'tween', duration: 0.4, ease: [0.22, 1, 0.36, 1] };
}

/** Gentle end settle when a 2-card step lands slightly off-center. */
function ribbonSettleTransition() {
    return { type: 'tween', duration: 0.28, ease: [0.22, 1, 0.36, 1] };
}

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

/** Ribbon slide cell — consistent width on mobile and desktop */
const RIBBON_CARD_SHELL =
    'flex min-h-[232px] w-[min(280px,calc(100vw-5.5rem))] shrink-0 flex-col items-stretch sm:min-h-[248px] md:w-[286px] lg:min-h-[264px] lg:w-[302px] transform-gpu';

const STATIC_CARD_SHELL_SINGLE =
    'flex min-h-[232px] w-full max-w-[302px] shrink-0 flex-col items-stretch sm:min-h-[248px] sm:w-[286px] lg:min-h-[264px] lg:w-[302px] transform-gpu';

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
 *
 * @param {number} logicalIdx
 * @param {HTMLDivElement} container
 * @param {HTMLDivElement} track
 * @param {(HTMLDivElement|null)[]} cards
 * @param {number} itemCount
 * @param {number} copies
 * @param {number} currentTrackX
 */
function trackXForCenteredLogical(logicalIdx, container, track, cards, itemCount, copies, currentTrackX) {
    const physical = focusedPhysicalIndex(logicalIdx, itemCount, copies);
    const card = cards[physical];
    if (!card) {
        return undefined;
    }

    const gr = container.getBoundingClientRect();
    const cw = gr.width;
    const tw = getDesktopTrackScrollExtent(track);
    const cr = card.getBoundingClientRect();
    const viewportCenterX = gr.left + cw / 2;
    const cardCenterX = cr.left + cr.width / 2;
    let x = currentTrackX - (cardCenterX - viewportCenterX);

    if (tw > cw) {
        const minX = cw - tw;
        x = Math.max(minX, Math.min(0, x));

        if (tw + x < cw - 1) {
            x = minX;
        }
    }

    return x;
}

/**
 * Horizontal ribbon carousel — same interaction on mobile and desktop (arrows, dots, focus + peek).
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
            if (!container || !track) {
                return undefined;
            }

            return trackXForCenteredLogical(
                logicalIdx,
                container,
                track,
                cardRefs.current,
                itemCount,
                copies,
                trackX.get(),
            );
        },
        [copies, itemCount, trackX],
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
     * Nudge track onto the centered target — instant for long ribbons, eased for 2-card decks.
     *
     * @param {number} targetX
     */
    const settleTrackTo = useCallback(
        async (targetX) => {
            const drift = Math.abs(trackX.get() - targetX);
            if (drift <= 0.5) {
                return;
            }

            if (itemCount === 2) {
                await animate(trackX, targetX, ribbonSettleTransition()).finished;

                return;
            }

            trackX.set(targetX);
        },
        [itemCount, trackX],
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

                let stepPx = getStepBetweenLogical(from, to);
                if (stepPx === 0) {
                    await new Promise((r) => requestAnimationFrame(r));
                    stepPx = getStepBetweenLogical(from, to);
                }
                if (stepPx === 0) {
                    return;
                }

                if (itemCount === 2) {
                    setRibbonActiveIndex(to);
                    ribbonActiveIndexRef.current = to;
                    await new Promise((r) => requestAnimationFrame(r));
                }

                const cur = trackX.get();

                await animate(trackX, cur - stepPx, netflixSlideTransition()).finished;

                const tx = alignTrackToLogical(to);
                if (tx !== undefined) {
                    await settleTrackTo(tx);
                }

                if (itemCount !== 2) {
                    setRibbonActiveIndex(to);
                    ribbonActiveIndexRef.current = to;
                }
            } finally {
                navBusyRef.current = false;
            }
        },
        [alignTrackToLogical, getStepBetweenLogical, itemCount, settleTrackTo, trackX],
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
                let stepPx = getStepBetweenLogical(from, bounded);
                if (stepPx === 0) {
                    await new Promise((r) => requestAnimationFrame(r));
                    stepPx = getStepBetweenLogical(from, bounded);
                }

                if (stepPx !== 0) {
                    if (itemCount === 2) {
                        setRibbonActiveIndex(bounded);
                        ribbonActiveIndexRef.current = bounded;
                        await new Promise((r) => requestAnimationFrame(r));
                    }

                    const cur = trackX.get();
                    await animate(trackX, cur - stepPx, netflixSlideTransition()).finished;
                }

                const tx = alignTrackToLogical(bounded);
                if (tx !== undefined) {
                    await settleTrackTo(tx);
                }

                if (itemCount !== 2) {
                    setRibbonActiveIndex(bounded);
                    ribbonActiveIndexRef.current = bounded;
                }
            } finally {
                navBusyRef.current = false;
            }
        },
        [alignTrackToLogical, getStepBetweenLogical, itemCount, settleTrackTo, trackX],
    );

    useEffect(() => {
        const bump = () => setGalleryResizeSeq((s) => s + 1);
        const ro = new ResizeObserver(bump);

        const gallery = galleryRef.current;
        const track = trackRef.current;
        if (gallery) {
            ro.observe(gallery);
        }
        if (track) {
            ro.observe(track);
        }

        return () => {
            ro.disconnect();
        };
    }, [itemCount]);

    useLayoutEffect(() => {
        if (itemCount === 0) {
            return;
        }
        let innerId = 0;
        const outerId = requestAnimationFrame(() => {
            innerId = requestAnimationFrame(() => {
                const tx = alignTrackToLogical(ribbonActiveIndexRef.current);
                if (tx !== undefined) {
                    trackX.set(tx);
                }
            });
        });

        return () => {
            cancelAnimationFrame(outerId);
            cancelAnimationFrame(innerId);
        };
    }, [alignTrackToLogical, galleryResizeSeq, itemCount, copies, trackX]);

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

    return (
        <div className="group relative w-full md:px-16 lg:px-20">
            <div className="relative w-full overflow-x-clip overflow-y-visible pb-2 pt-4 outline-none ring-0">
                <div
                    ref={galleryRef}
                    className="relative min-h-[10rem] w-full min-w-0 px-2 py-4 outline-none ring-0 md:px-16 md:py-6 lg:px-20"
                >
                    <div className="relative overflow-x-clip">
                        <motion.div
                            ref={trackRef}
                            style={{ x: trackX }}
                            className="relative z-0 flex w-max shrink-0 flex-nowrap items-stretch gap-3 will-change-transform transform-gpu md:gap-6"
                        >
                            {Array.from({ length: copies }, (_, copy) =>
                                items.map((item, idx) => {
                                    const physicalIdx = copy * itemCount + idx;
                                    const isRibbonFocus =
                                        physicalIdx === focusedPhysicalIndex(ribbonActiveIndex, itemCount, copies);

                                    return (
                                        <div
                                            key={`ribbon-${copy}-${getKey(item, idx)}`}
                                            ref={(el) => {
                                                cardRefs.current[physicalIdx] = el;
                                            }}
                                            data-ribbon-card=""
                                            className={`${RIBBON_CARD_SHELL} ${isRibbonFocus ? 'z-[5]' : 'z-0'}`}
                                        >
                                            <motion.div
                                                className={`flex min-h-0 flex-1 flex-col rounded-[12px] ${isRibbonFocus ? 'shadow-md shadow-[#262A22]/10' : ''}`}
                                                style={{ transformOrigin: 'center center' }}
                                                animate={{
                                                    scale: isRibbonFocus ? RIBBON_FOCUS_SCALE : RIBBON_NEIGHBOR_SCALE,
                                                    opacity: isRibbonFocus ? RIBBON_FOCUS_OPACITY : RIBBON_NEIGHBOR_OPACITY,
                                                }}
                                                transition={netflixSlideTransition()}
                                                whileHover={
                                                    isRibbonFocus
                                                        ? {
                                                              scale: 1.03,
                                                              transition: { duration: 0.2, ease: [0.22, 1, 0.36, 1] },
                                                          }
                                                        : { scale: RIBBON_NEIGHBOR_SCALE }
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
                            ).flat()}
                        </motion.div>
                    </div>
                </div>

                <div className="pointer-events-none absolute inset-y-0 left-0 z-[110] w-11 border-none bg-transparent shadow-none outline-none ring-0 md:w-14">
                    <button
                        type="button"
                        aria-label="Previous meal"
                        onClick={() => void prevRibbon()}
                        disabled={itemCount <= 1}
                        className="pointer-events-auto flex h-full min-h-[3rem] w-full items-center justify-center rounded-none border-0 bg-transparent text-[#262A22] shadow-none outline-none ring-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]/35 focus-visible:ring-offset-0 disabled:pointer-events-none disabled:opacity-30"
                    >
                        <svg className="h-8 w-8 shrink-0 md:h-9 md:w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.25} d="M15 18l-6-6 6-6" />
                        </svg>
                    </button>
                </div>
                <div className="pointer-events-none absolute inset-y-0 right-0 z-[110] w-11 border-none bg-transparent shadow-none outline-none ring-0 md:w-14">
                    <button
                        type="button"
                        aria-label="Next meal"
                        onClick={() => void nextRibbon()}
                        disabled={itemCount <= 1}
                        className="pointer-events-auto flex h-full min-h-[3rem] w-full items-center justify-center rounded-none border-0 bg-transparent text-[#262A22] shadow-none outline-none ring-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]/35 focus-visible:ring-offset-0 disabled:pointer-events-none disabled:opacity-30"
                    >
                        <svg className="h-8 w-8 shrink-0 md:h-9 md:w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.25} d="M9 18l6-6-6-6" />
                        </svg>
                    </button>
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
