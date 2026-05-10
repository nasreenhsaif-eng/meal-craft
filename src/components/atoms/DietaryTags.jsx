const BASE =
    'box-border inline-flex h-[26px] min-h-[26px] max-w-full shrink-0 items-center justify-center rounded-[14.5px] px-[14px] font-montserrat text-[11px] font-bold uppercase leading-none whitespace-nowrap';

/**
 * @param {{ label: string; className?: string }} props
 */
export function DietaryTag({ label, className = '' }) {
    return (
        <span className={`${BASE} bg-[#556C37] text-white shadow-sm ${className}`.trim()}>
            {label}
        </span>
    );
}

/**
 * @param {{ tags: string[]; className?: string }} props
 */
export default function DietaryTags({ tags, className = '' }) {
    if (!tags?.length) {
        return null;
    }
    return (
        <div className={`flex flex-wrap gap-2 ${className}`.trim()} role="list" aria-label="Dietary tags">
            {tags.map((t) => (
                <span key={t} role="listitem">
                    <DietaryTag label={t} />
                </span>
            ))}
        </div>
    );
}

