/**
 * Full-bleed kitchen plate + soft left veil — matches the Framer Meal Craft hero background.
 * Top of the plate is cropped so the hero reads shorter; image is anchored to the bottom edge.
 * Foreground media (micronutrients + hand) lives in LandingPage so copy can share the row.
 *
 * @param {{ className?: string }} props
 */
export default function LandingAppPreview({ className = '' }) {
    return (
        <div
            className={['pointer-events-none absolute inset-0 z-0 overflow-hidden', className]
                .join(' ')
                .trim()}
            aria-hidden="true"
        >
            <img
                src="/images/landing/kitchen-hero.jpg?v=2"
                alt=""
                className="absolute inset-x-0 bottom-0 h-[128%] w-full object-cover object-[62%_42%]"
                width={1280}
                height={720}
                decoding="async"
            />
            <div className="absolute inset-0 bg-gradient-to-r from-white from-0% via-white/85 via-32% to-transparent to-58%" />
        </div>
    );
}
