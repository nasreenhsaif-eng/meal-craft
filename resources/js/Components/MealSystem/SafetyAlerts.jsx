const BASE =
    'box-border inline-flex h-[26px] min-h-[26px] max-w-full shrink-0 items-center justify-center rounded-[14.5px] px-[14px] font-montserrat text-[11px] font-bold uppercase leading-none whitespace-nowrap text-white';

export function SafetyAlert({ label, variant = 'allergy', className = '' }) {
    const isG6pd = variant === 'g6pd' || String(label).toUpperCase().includes('G6PD');
    const tone = isG6pd ? 'bg-[#D12A1C] shadow-sm ring-2 ring-black/25' : 'bg-[#D12A1C] shadow-sm';

    return (
        <span
            className={`${BASE} ${tone} print:bg-[#D12A1C] print:text-white print:font-extrabold print:[print-color-adjust:exact] print:[-webkit-print-color-adjust:exact] ${className}`.trim()}
        >
            {label}
        </span>
    );
}

export default function SafetyAlerts({ alerts, className = '' }) {
    if (!alerts?.length) {
        return null;
    }

    return (
        <div className={`flex flex-wrap gap-2 ${className}`.trim()} role="list" aria-label="Safety alerts">
            {alerts.map((a) => (
                <span key={`${a.variant ?? 'allergy'}-${a.label}`} role="listitem">
                    <SafetyAlert label={a.label} variant={a.variant ?? 'allergy'} />
                </span>
            ))}
        </div>
    );
}

