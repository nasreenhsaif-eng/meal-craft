const BASE =
    'box-border inline-flex h-[26px] min-h-[26px] max-w-full shrink-0 items-center justify-center rounded-[14.5px] px-[14px] font-montserrat text-[11px] font-bold uppercase leading-none whitespace-nowrap';

export function ProtocolTag({ label, className = '' }) {
    return (
        <span
            className={[
                BASE,
                'bg-[#556C37]/10 text-[#556C37]',
                'ring-1 ring-[#556C37]/25',
                'shadow-sm',
                className,
            ].join(' ')}
        >
            {label}
        </span>
    );
}

export default function ProtocolTags({ tags, className = '' }) {
    if (!tags?.length) {
        return null;
    }

    return (
        <div className={`flex flex-wrap gap-2 ${className}`.trim()} role="list" aria-label="Protocols">
            {tags.map((t) => (
                <span key={t} role="listitem">
                    <ProtocolTag label={t} />
                </span>
            ))}
        </div>
    );
}

