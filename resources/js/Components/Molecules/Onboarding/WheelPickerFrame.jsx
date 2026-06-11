import { WHEEL_HEIGHT, WHEEL_ITEM_HEIGHT, WHEEL_PAD_COUNT } from './wheelConstants.js';

/**
 * Shared 3D wheel frame with a static center selection band and edge fade mask.
 *
 * @param {{ children: import('react').ReactNode; className?: string }} props
 */
export function WheelPickerFrame({ children, className = '' }) {
    const selectionTop = WHEEL_PAD_COUNT * WHEEL_ITEM_HEIGHT;

    return (
        <div
            className={[
                'relative isolate w-full overflow-hidden rounded-[16px] border-0 bg-transparent shadow-none',
                className,
            ].join(' ')}
            style={{ height: `${WHEEL_HEIGHT}px` }}
        >
            <div
                className="pointer-events-none absolute inset-x-2 rounded-[10px] bg-[#6E8C47]/10 sm:inset-x-3"
                style={{
                    top: `${selectionTop}px`,
                    height: `${WHEEL_ITEM_HEIGHT}px`,
                }}
                aria-hidden
            />
            <div className="relative flex h-full w-full [transform-style:preserve-3d]">{children}</div>
        </div>
    );
}

export default WheelPickerFrame;
