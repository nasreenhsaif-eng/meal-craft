import { useCallback, useEffect, useRef } from 'react';
import {
    HEIGHT_WHEEL_ITEM_HEIGHT,
    HEIGHT_WHEEL_PAD_COUNT,
    HEIGHT_WHEEL_VIEWPORT_HEIGHT,
} from './heightWheelConstants.js';

/**
 * @param {Array<string | number>} items
 * @param {string | number} value
 * @returns {'selected' | 'adjacent' | 'far'}
 */
function rowState(items, value, item) {
    const selectedIndex = items.findIndex((entry) => entry === value);
    const itemIndex = items.findIndex((entry) => entry === item);

    if (selectedIndex < 0 || itemIndex < 0) {
        return 'far';
    }

    const distance = Math.abs(itemIndex - selectedIndex);

    if (distance === 0) {
        return 'selected';
    }

    if (distance === 1) {
        return 'adjacent';
    }

    return 'far';
}

/**
 * Three-row snap wheel column with faded neighbors and optional unit label on the active row.
 *
 * @param {{
 *   items: Array<string | number>;
 *   value: string | number;
 *   onChange: (value: string | number) => void;
 *   formatItem?: (value: string | number) => string;
 *   ariaLabel: string;
 *   unitLabel?: string;
 *   columnClassName?: string;
 * }} props
 */
export function HeightSnapColumn({
    items,
    value,
    onChange,
    formatItem = (item) => String(item),
    ariaLabel,
    unitLabel,
    columnClassName = '',
}) {
    const listRef = useRef(null);
    const scrollTimeoutRef = useRef(null);

    const selectedIndex = items.findIndex((item) => item === value);

    useEffect(() => {
        if (!listRef.current || selectedIndex < 0) {
            return;
        }

        listRef.current.scrollTop = selectedIndex * HEIGHT_WHEEL_ITEM_HEIGHT;
    }, [selectedIndex, items.length]);

    const syncFromScroll = useCallback(() => {
        if (!listRef.current || items.length === 0) {
            return;
        }

        const nextIndex = Math.min(
            items.length - 1,
            Math.max(0, Math.round(listRef.current.scrollTop / HEIGHT_WHEEL_ITEM_HEIGHT)),
        );
        const nextValue = items[nextIndex];

        if (nextValue !== value) {
            onChange(nextValue);
        }
    }, [items, onChange, value]);

    const handleScroll = () => {
        window.clearTimeout(scrollTimeoutRef.current);
        scrollTimeoutRef.current = window.setTimeout(syncFromScroll, 90);
    };

    return (
        <div className={`relative min-w-0 w-full ${columnClassName}`.trim()} aria-label={ariaLabel}>
            <ul
                ref={listRef}
                onScroll={handleScroll}
                style={{ height: `${HEIGHT_WHEEL_VIEWPORT_HEIGHT}px` }}
                className="snap-y snap-mandatory overflow-y-auto overscroll-y-contain scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                {Array.from({ length: HEIGHT_WHEEL_PAD_COUNT }).map((_, index) => (
                    <li
                        key={`pad-start-${index}`}
                        style={{ height: `${HEIGHT_WHEEL_ITEM_HEIGHT}px` }}
                        className="shrink-0 snap-start"
                        aria-hidden
                    />
                ))}
                {items.map((item) => {
                    const state = rowState(items, value, item);

                    return (
                        <li
                            key={String(item)}
                            style={{ height: `${HEIGHT_WHEEL_ITEM_HEIGHT}px` }}
                            className={[
                                'mx-1 flex shrink-0 snap-center items-center justify-center whitespace-nowrap rounded-[10px] px-2 font-montserrat leading-none',
                                'transition-[opacity,font-size,font-weight,color,background-color] duration-150',
                                state === 'selected'
                                    ? 'bg-[#6E8C47]/10 text-lg font-bold text-[#364153] opacity-100'
                                    : state === 'adjacent'
                                      ? 'bg-transparent text-sm font-medium text-[#6B7280] opacity-30'
                                      : 'bg-transparent text-sm font-medium text-[#6B7280] opacity-20',
                            ].join(' ')}
                        >
                            <span>{formatItem(item)}</span>
                            {state === 'selected' && unitLabel ? (
                                <span className="ml-1.5 shrink-0 text-sm font-semibold text-[#364153]">{unitLabel}</span>
                            ) : null}
                        </li>
                    );
                })}
                {Array.from({ length: HEIGHT_WHEEL_PAD_COUNT }).map((_, index) => (
                    <li
                        key={`pad-end-${index}`}
                        style={{ height: `${HEIGHT_WHEEL_ITEM_HEIGHT}px` }}
                        className="shrink-0 snap-start"
                        aria-hidden
                    />
                ))}
            </ul>
        </div>
    );
}

export default HeightSnapColumn;
