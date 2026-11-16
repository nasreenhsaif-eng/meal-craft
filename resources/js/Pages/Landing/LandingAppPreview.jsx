/**
 * Full-bleed rebuilt rustic kitchen hero (candidate 05 scene) with hand + phone as one photo.
 * Soft veil on the left keeps the Healing Through Whole Foods copy readable.
 *
 * @param {{ className?: string }} props
 */
export default function LandingAppPreview({ className = '' }) {
    return (
        <div
            className={[
                'pointer-events-none absolute inset-0 z-0 overflow-hidden',
                className,
            ]
                .join(' ')
                .trim()}
            aria-hidden="true"
        >
            <img
                src="/images/landing/hand-holding-phone.png?v=25"
                alt=""
                className="h-full w-full object-cover object-[62%_28%]"
                width={1280}
                height={720}
                decoding="async"
            />
            <div className="absolute inset-0 bg-gradient-to-r from-white from-0% via-white/85 via-32% to-transparent to-58%" />
        </div>
    );
}
