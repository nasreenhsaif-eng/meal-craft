/** Exact diet-protocol stroke glyphs (traced SVG paths). */
export const DIET_PROTOCOL_ICON_STROKE = 2.25;

const ICON_BASE = 'block h-9 w-9 shrink-0';

const STROKE = {
    stroke: 'currentColor',
    strokeWidth: DIET_PROTOCOL_ICON_STROKE,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
};

/**
 * @param {{ className?: string; viewBox: string; children: import('react').ReactNode }} props
 */
function DietIcon({ className = '', viewBox, children }) {
    return (
        <svg
            className={`${ICON_BASE} ${className}`.trim()}
            viewBox={viewBox}
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            {children}
        </svg>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconBalanced({ className = '' }) {
    return (
        <DietIcon className={className} viewBox="0 0 36 36">
            <path
                d="M30.375 18C30.375 24.8345 24.8345 30.375 18 30.375C11.1655 30.375 5.625 24.8345 5.625 18C5.625 11.1655 11.1655 5.625 18 5.625C24.8345 5.625 30.375 11.1655 30.375 18Z"
                {...STROKE}
            />
            <path
                d="M26.4375 18C26.4375 22.6599 22.6599 26.4375 18 26.4375C13.3401 26.4375 9.5625 22.6599 9.5625 18C9.5625 13.3401 13.3401 9.5625 18 9.5625C22.6599 9.5625 26.4375 13.3401 26.4375 18Z"
                {...STROKE}
                strokeOpacity={0.35}
            />
            <path d="M18 5.625V30.375" {...STROKE} />
            <path d="M18 18H30.375" {...STROKE} />
        </DietIcon>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconKetobiotic({ className = '' }) {
    return (
        <DietIcon className={className} viewBox="0 51 36 36">
            <path
                d="M18 55.5C13.275 55.5 9.5625 63.6 9.5625 71.475C9.5625 77.55 13.275 82.5 18 82.5C22.725 82.5 26.4375 77.55 26.4375 71.475C26.4375 63.6 22.725 55.5 18 55.5Z"
                {...STROKE}
            />
            <path
                d="M22.2188 72.9375C22.2188 75.2675 20.33 77.1562 18 77.1562C15.67 77.1562 13.7812 75.2675 13.7812 72.9375C13.7812 70.6075 15.67 68.7188 18 68.7188C20.33 68.7188 22.2188 70.6075 22.2188 72.9375Z"
                {...STROKE}
            />
        </DietIcon>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconSickleCellWarrior({ className = '' }) {
    return (
        <DietIcon className={className} viewBox="0 102.5 36 36">
            <path
                d="M18 106.5L29.25 111V119.438C29.25 126.862 24.4125 132.15 18 134.062C11.5875 132.15 6.75 126.862 6.75 119.438V111L18 106.5Z"
                {...STROKE}
            />
            <path
                d="M18 114.938C18 114.938 13.725 119.775 13.725 122.7C13.725 125.062 15.6375 126.975 18 126.975C20.3625 126.975 22.275 125.062 22.275 122.7C22.275 119.775 18 114.938 18 114.938Z"
                {...STROKE}
            />
        </DietIcon>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconThyroid({ className = '' }) {
    return (
        <DietIcon className={className} viewBox="0 152.5 36 36">
            <path d="M18 164.25V178.875" {...STROKE} />
            <path d="M16.3125 161.438L18 164.25L19.6875 161.438" {...STROKE} />
            <path
                d="M18 168.188C15.1875 163.688 7.3125 162.563 6.1875 167.625C5.4 171.225 9.225 173.25 12.9375 172.688C9.5625 173.475 7.65 177.188 9.9 179.775C12.15 182.25 16.3125 179.438 18 176.063"
                {...STROKE}
            />
            <path
                d="M18 168.188C20.8125 163.688 28.6875 162.563 29.8125 167.625C30.6 171.225 26.775 173.25 23.0625 172.688C26.4375 173.475 28.35 177.188 26.1 179.775C23.85 182.25 19.6875 179.438 18 176.063"
                {...STROKE}
            />
        </DietIcon>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconCycleSync({ className = '' }) {
    return (
        <DietIcon className={className} viewBox="0 204 36 36">
            <path
                d="M31.5 222C31.5 229.456 25.4558 235.5 18 235.5C10.5442 235.5 4.5 229.456 4.5 222C4.5 214.544 10.5442 208.5 18 208.5C25.4558 208.5 31.5 214.544 31.5 222Z"
                stroke="currentColor"
                strokeWidth={1.6875}
            />
            <path
                d="M27.225 222C27.225 227.095 23.0948 231.225 18 231.225C12.9052 231.225 8.775 227.095 8.775 222C8.775 216.905 12.9052 212.775 18 212.775C23.0948 212.775 27.225 216.905 27.225 222Z"
                stroke="currentColor"
                strokeWidth={1.2375}
            />
            <path
                d="M18 214.35C18.8698 214.35 19.575 213.645 19.575 212.775C19.575 211.905 18.8698 211.2 18 211.2C17.1302 211.2 16.425 211.905 16.425 212.775C16.425 213.645 17.1302 214.35 18 214.35Z"
                fill="currentColor"
            />
            <path
                d="M27.225 223.575C28.0948 223.575 28.8 222.87 28.8 222C28.8 221.13 28.0948 220.425 27.225 220.425C26.3552 220.425 25.65 221.13 25.65 222C25.65 222.87 26.3552 223.575 27.225 223.575Z"
                fill="currentColor"
            />
            <path
                d="M18 232.8C18.8698 232.8 19.575 232.095 19.575 231.225C19.575 230.355 18.8698 229.65 18 229.65C17.1302 229.65 16.425 230.355 16.425 231.225C16.425 232.095 17.1302 232.8 18 232.8Z"
                fill="currentColor"
            />
            <path
                d="M8.775 223.575C9.64485 223.575 10.35 222.87 10.35 222C10.35 221.13 9.64485 220.425 8.775 220.425C7.90515 220.425 7.2 221.13 7.2 222C7.2 222.87 7.90515 223.575 8.775 223.575Z"
                fill="currentColor"
            />
        </DietIcon>
    );
}

/**
 * @param {{ className?: string }} props
 */
export function IconNutrientDense({ className = '' }) {
    return (
        <DietIcon className={className} viewBox="0 255 36 36">
            <path d="M10.125 285.375C12.6 283.35 15.1875 282.562 18 282.562C20.8125 282.562 23.4 283.35 25.875 285.375" {...STROKE} />
            <path d="M18 282.562V269.625" {...STROKE} />
            <path
                d="M18 273.562C12.9375 273.562 9.5625 270.75 9.5625 266.25C14.0625 266.25 17.4375 268.725 18 273.562Z"
                {...STROKE}
            />
            <path
                d="M18 270.75C23.0625 270.75 26.4375 267.938 26.4375 263.438C21.9375 263.438 18.5625 265.912 18 270.75Z"
                {...STROKE}
            />
            <path d="M27.5625 274.688V278.062M25.875 276.375H29.25" {...STROKE} />
            <path d="M12.375 261.75V264M11.25 262.875H13.5" {...STROKE} />
        </DietIcon>
    );
}

/** @deprecated Use {@link IconBalanced} */
export const IconBalancedPlate = IconBalanced;

/** @deprecated Use {@link IconCycleSync} */
export const IconCycleSyncSeal = IconCycleSync;

/** @deprecated Use {@link IconKetobiotic} */
export const IconKetogenic = IconKetobiotic;
