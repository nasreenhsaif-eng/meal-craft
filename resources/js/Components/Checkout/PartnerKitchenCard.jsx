import { useId, useState } from 'react';
import Button from '../Atoms/Button/Button.jsx';
import MealCraftLogo from '../Atoms/Logo/MealCraftLogo.jsx';
import TextLink from '../Atoms/TextLink.jsx';

/**
 * @param {number} fill 0–1
 */
function RatingStar({ fill }) {
    const clamped = Math.min(1, Math.max(0, fill));
    const clipId = `mc-star-clip-${useId().replaceAll(':', '')}`;

    return (
        <span className="relative inline-flex h-4 w-4 text-brand-secondary" aria-hidden="true">
            <svg className="absolute inset-0 text-border-light" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 1.8 12.4 7l5.6.8-4 3.9.9 5.6L10 14.6 4.9 17.3l.9-5.6-4-3.9L7.6 7 10 1.8Z" />
            </svg>
            {clamped > 0 ? (
                <svg className="absolute inset-0" viewBox="0 0 20 20" fill="currentColor">
                    <defs>
                        <clipPath id={clipId}>
                            <rect width={20 * clamped} height="20" />
                        </clipPath>
                    </defs>
                    <path
                        clipPath={`url(#${clipId})`}
                        d="M10 1.8 12.4 7l5.6.8-4 3.9.9 5.6L10 14.6 4.9 17.3l.9-5.6-4-3.9L7.6 7 10 1.8Z"
                    />
                </svg>
            ) : null}
        </span>
    );
}

/**
 * @param {{ rating: number; max?: number }} props
 */
function RatingStars({ rating, max = 5 }) {
    const value = Number.isFinite(rating) ? rating : 0;

    return (
        <span className="inline-flex items-center gap-0.5" aria-hidden="true">
            {Array.from({ length: max }, (_, index) => (
                <RatingStar key={index} fill={value - index} />
            ))}
        </span>
    );
}

/**
 * Horizontal partner-kitchen card for checkout — logo, rating, price, and select action.
 *
 * @param {{
 *   name: string;
 *   logoUrl?: string | null;
 *   logoAlt?: string;
 *   rating?: number;
 *   reviewCount?: number;
 *   price?: string | number;
 *   billingCadence?: string | null;
 *   selectLabel?: string;
 *   onReviewsClick?: () => void;
 *   onSelect?: () => void;
 *   className?: string;
 * }} props
 */
export default function PartnerKitchenCard({
    name,
    logoUrl = null,
    logoAlt,
    rating = 0,
    reviewCount = 0,
    price,
    billingCadence = null,
    selectLabel,
    onReviewsClick,
    onSelect,
    className = '',
}) {
    const [logoFailed, setLogoFailed] = useState(false);
    const showLogo = Boolean(logoUrl) && !logoFailed;
    const resolvedSelectLabel = selectLabel ?? (name ? `Select ${name}` : 'Select Kitchen');
    const reviewLabel = `${Number(reviewCount).toLocaleString()} ${Number(reviewCount) === 1 ? 'review' : 'reviews'}`;
    const ratingValue = Number(rating);
    const priceLabel = price == null || price === '' ? null : String(price);

    return (
        <article
            className={[
                'flex w-full flex-col overflow-hidden rounded-[16px] border border-border-light bg-white shadow-sm',
                'transition-all duration-200 ease-in-out hover:border-brand-primary-pressed/40 hover:shadow-md',
                'sm:flex-row',
                className,
            ].join(' ')}
        >
            <div className="aspect-[4/3] w-full shrink-0 overflow-hidden bg-grey-96 sm:aspect-square sm:w-44 md:w-52">
                {showLogo ? (
                    <img
                        src={logoUrl}
                        alt={logoAlt ?? `${name} logo`}
                        className="h-full w-full object-contain p-4"
                        onError={() => setLogoFailed(true)}
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center">
                        <MealCraftLogo variant="seal-sm" width={64} alt="" className="opacity-80" />
                    </div>
                )}
            </div>

            <div className="flex min-w-0 flex-1 flex-col gap-3 p-5 sm:p-6">
                <h3 className="font-montserrat text-xl font-semibold tracking-tight text-brand-primary-pressed sm:text-2xl">
                    {name}
                </h3>

                <div className="flex flex-wrap items-center gap-2">
                    <span
                        className="inline-flex items-center gap-1.5"
                        aria-label={`${ratingValue.toFixed(1)} out of 5 stars`}
                    >
                        <RatingStars rating={ratingValue} />
                        <span className="font-montserrat text-sm font-bold tabular-nums text-brand-primary-pressed">
                            {ratingValue.toFixed(1)}
                        </span>
                    </span>
                    <TextLink
                        href="#reviews"
                        className="font-montserrat text-sm underline"
                        onClick={(event) => {
                            event.preventDefault();
                            onReviewsClick?.();
                        }}
                    >
                        {reviewLabel}
                    </TextLink>
                </div>

                {priceLabel ? (
                    <p className="font-montserrat text-lg font-bold tabular-nums text-brand-primary-pressed">
                        {priceLabel}
                        {billingCadence ? (
                            <span className="ms-1 text-sm font-medium text-grey-33">/ {billingCadence}</span>
                        ) : null}
                    </p>
                ) : null}

                <div className="mt-auto pt-1 sm:flex sm:justify-end">
                    <Button
                        type="button"
                        label={resolvedSelectLabel}
                        onClick={onSelect}
                        className="w-full sm:w-auto"
                    />
                </div>
            </div>
        </article>
    );
}
