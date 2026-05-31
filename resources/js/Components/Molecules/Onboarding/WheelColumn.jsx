import { useCallback, useEffect, useRef } from 'react';
import { WHEEL_ITEM_HEIGHT, WHEEL_PAD_COUNT } from './wheelConstants.js';

/**
 * Single scroll-snap column shared by onboarding wheel pickers.
 *
 * @param {{
 *   items: Array<string | number>;
 *   value: string | number;
 *   onChange: (value: string | number) => void;
 *   formatItem?: (value: string | number) => string;
 *   ariaLabel: string;
 *   columnClassName?: string;
 * }} props
 */
export function WheelColumn({
    items,
    value,
    onChange,
    formatItem = (item) => String(item),
    ariaLabel,
    columnClassName = '',
}) {
    const listRef = useRef(null);
    const scrollTimeoutRef = useRef(null);

    const selectedIndex = items.findIndex((item) => item === value);

    useEffect(() => {
        if (!listRef.current || selectedIndex < 0) {
            return;
        }

        listRef.current.scrollTop = selectedIndex * WHEEL_ITEM_HEIGHT;
    }, [selectedIndex, items.length]);

    const syncFromScroll = useCallback(() => {
        if (!listRef.current || items.length === 0) {
            return;
        }

        const nextIndex = Math.min(
            items.length - 1,
            Math.max(0, Math.round(listRef.current.scrollTop / WHEEL_ITEM_HEIGHT)),
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
        <div className={`relative min-w-0 flex-1 ${columnClassName}`.trim()} aria-label={ariaLabel}>
            <ul
                ref={listRef}
                onScroll={handleScroll}
                className="h-[220px] snap-y snap-mandatory overflow-y-auto overscroll-y-contain scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                {Array.from({ length: WHEEL_PAD_COUNT }).map((_, index) => (
                    <li key={`pad-start-${index}`} className="h-11 shrink-0 snap-start" aria-hidden />
                ))}
                {items.map((item) => {
                    const selected = item === value;

                    return (
                        <li
                            key={String(item)}
                            className={[
                                'mx-1 flex h-11 shrink-0 snap-center items-center justify-center rounded-[10px] px-1 font-montserrat text-base leading-none transition-[opacity,font-weight,color,background-color] sm:mx-2 sm:px-2',
                                selected
                                    ? 'bg-[#6E8C47]/10 font-bold text-[#364153] opacity-100'
                                    : 'bg-transparent font-medium text-[#777777] opacity-40',
                            ].join(' ')}
                        >
                            {formatItem(item)}
                        </li>
                    );
                })}
                {Array.from({ length: WHEEL_PAD_COUNT }).map((_, index) => (
                    <li key={`pad-end-${index}`} className="h-11 shrink-0 snap-start" aria-hidden />
                ))}
            </ul>
        </div>
    );
}

export default WheelColumn;
