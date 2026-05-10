const BASE =
    'box-border inline-flex h-[26px] min-h-[26px] max-w-full shrink-0 items-center justify-center rounded-[14.5px] px-[14px] font-montserrat text-[11px] font-bold uppercase leading-none whitespace-nowrap';

/**
 * @param {{ label: string; className?: string }} props
 */
export function PreferenceTag({ label, className = '' }) {
    return (
        <span
            className={`${BASE} bg-[#727A87] text-white shadow-sm print:bg-[#727A87] print:text-white print:font-bold print:[print-color-adjust:exact] print:[-webkit-print-color-adjust:exact] ${className}`.trim()}
        >
            {label}
        </span>
    );
}

/**
 * Grey preference tags (dislikes / requests / protocol).
 *
 * @param {{ tags: string[]; className?: string }} props
 */
export default function PreferenceTags({ tags, className = '' }) {
    if (!tags?.length) {
        return null;
    }

    return (
        <div className={`flex flex-wrap gap-2 ${className}`.trim()} role="list" aria-label="Preferences">
            {tags.map((t) => (
                <span key={t} role="listitem">
                    <PreferenceTag label={t} />
                </span>
            ))}
        </div>
    );
}

