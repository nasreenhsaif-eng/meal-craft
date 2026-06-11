import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { WHEEL_HEIGHT, WHEEL_ITEM_HEIGHT, WHEEL_PAD_COUNT } from './wheelConstants.js';
import {
    getWheelItemOffset,
    getWheelItemVisual,
    WHEEL_MASK_IMAGE,
} from './wheelTransformUtils.js';

/**
 * @param {Array<string | number>} items
 * @param {string | number} value
 * @returns {number}
 */
function findSelectedIndex(items, value) {
    const normalizedValue = Number(value);

    if (Number.isFinite(normalizedValue)) {
        const numericIndex = items.findIndex((item) => Number(item) === normalizedValue);

        if (numericIndex >= 0) {
            return numericIndex;
        }
    }

    return items.findIndex((item) => item === value);
}

/**
 * Single 3D scroll-snap column shared by onboarding wheel pickers.
 *
 * @param {{
 *   items: Array<string | number>;
 *   value: string | number;
 *   onChange: (value: string | number) => void;
 *   formatItem?: (value: string | number) => string;
 *   ariaLabel: string;
 *   unitLabel?: string;
 *   columnClassName?: string;
 *   visible?: boolean;
 * }} props
 */
export function WheelColumn({
    items,
    value,
    onChange,
    formatItem = (item) => String(item),
    ariaLabel,
    unitLabel,
    columnClassName = '',
    visible = true,
}) {
    const listRef = useRef(null);
    const scrollTimeoutRef = useRef(null);
    const rafRef = useRef(null);
    const [scrollTop, setScrollTop] = useState(0);

    const selectedIndex = findSelectedIndex(items, value);

    const syncScrollPosition = useCallback(() => {
        if (!listRef.current || selectedIndex < 0) {
            return;
        }

        const nextScrollTop = selectedIndex * WHEEL_ITEM_HEIGHT;
        listRef.current.scrollTop = nextScrollTop;
        setScrollTop(nextScrollTop);
    }, [selectedIndex]);

    useLayoutEffect(() => {
        if (!visible) {
            return;
        }

        syncScrollPosition();
    }, [visible, syncScrollPosition, items.length]);

    useEffect(() => {
        if (!visible) {
            return;
        }

        const node = listRef.current;

        if (!node) {
            return;
        }

        const resyncWhenVisible = () => {
            if (node.offsetParent !== null || node.getClientRects().length > 0) {
                syncScrollPosition();
            }
        };

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    requestAnimationFrame(() => {
                        syncScrollPosition();
                    });
                }
            },
            { threshold: 0.01 },
        );

        observer.observe(node);
        window.addEventListener('resize', resyncWhenVisible);

        return () => {
            observer.disconnect();
            window.removeEventListener('resize', resyncWhenVisible);
        };
    }, [visible, syncScrollPosition]);

    const syncFromScroll = useCallback(() => {
        if (!listRef.current || items.length === 0) {
            return;
        }

        const currentScrollTop = listRef.current.scrollTop;
        setScrollTop(currentScrollTop);

        const nextIndex = Math.min(
            items.length - 1,
            Math.max(0, Math.round(currentScrollTop / WHEEL_ITEM_HEIGHT)),
        );
        const nextValue = items[nextIndex];

        if (nextValue !== value && Number(nextValue) !== Number(value)) {
            onChange(nextValue);
        }
    }, [items, onChange, value]);

    const handleScroll = () => {
        if (rafRef.current) {
            window.cancelAnimationFrame(rafRef.current);
        }

        rafRef.current = window.requestAnimationFrame(() => {
            if (listRef.current) {
                setScrollTop(listRef.current.scrollTop);
            }
        });

        window.clearTimeout(scrollTimeoutRef.current);
        scrollTimeoutRef.current = window.setTimeout(syncFromScroll, 90);
    };

    return (
        <div
            className={`relative min-w-0 flex-1 overflow-hidden [perspective:1000px] [transform-style:preserve-3d] ${columnClassName}`.trim()}
            style={{ height: `${WHEEL_HEIGHT}px` }}
            aria-label={ariaLabel}
        >
            <ul
                ref={listRef}
                onScroll={handleScroll}
                style={{
                    height: `${WHEEL_HEIGHT}px`,
                    WebkitMaskImage: WHEEL_MASK_IMAGE,
                    maskImage: WHEEL_MASK_IMAGE,
                }}
                className="snap-y snap-mandatory overflow-x-hidden overflow-y-auto overscroll-y-contain scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                {Array.from({ length: WHEEL_PAD_COUNT }).map((_, index) => (
                    <li
                        key={`pad-start-${index}`}
                        style={{ height: `${WHEEL_ITEM_HEIGHT}px` }}
                        className="shrink-0 snap-start"
                        aria-hidden
                    />
                ))}
                {items.map((item, itemIndex) => {
                    const offset = getWheelItemOffset(itemIndex, scrollTop);
                    const visual = getWheelItemVisual(offset);

                    return (
                        <li
                            key={String(item)}
                            style={{
                                height: `${WHEEL_ITEM_HEIGHT}px`,
                                opacity: visual.opacity,
                                transform: `rotateX(${visual.rotateX}deg) scale(${visual.scale})`,
                                transformStyle: 'preserve-3d',
                                backfaceVisibility: 'hidden',
                            }}
                            className={[
                                'mx-1 flex shrink-0 snap-center items-center justify-center rounded-[10px] px-1 font-montserrat text-base leading-none will-change-transform sm:mx-2 sm:px-2',
                                visual.tier === 'selected'
                                    ? 'font-bold text-[#364153]'
                                    : 'font-medium text-[#6B7280]',
                                visual.tier === 'hidden' ? 'pointer-events-none' : '',
                            ].join(' ')}
                        >
                            <span>{formatItem(item)}</span>
                            {visual.tier === 'selected' && unitLabel ? (
                                <span className="ml-1.5 shrink-0 text-sm font-semibold text-[#364153]">{unitLabel}</span>
                            ) : null}
                        </li>
                    );
                })}
                {Array.from({ length: WHEEL_PAD_COUNT }).map((_, index) => (
                    <li
                        key={`pad-end-${index}`}
                        style={{ height: `${WHEEL_ITEM_HEIGHT}px` }}
                        className="shrink-0 snap-start"
                        aria-hidden
                    />
                ))}
            </ul>
        </div>
    );
}

export default WheelColumn;
