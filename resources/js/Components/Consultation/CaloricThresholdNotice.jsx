import {
    CALORIC_THRESHOLD_NOTICE,
    isBelowMicronutrientEnforcedCalories,
    MICRONUTRIENT_ENFORCED_MIN_CALORIES,
} from '../../meal-library/caloricThresholdNotice.ts';
import { ENFORCED_MICRONUTRIENT_TIERS } from '../../meal-library/nutrientDailyRdi.ts';

/**
 * @param {object} props
 * @param {number} [props.planTierCalories]
 */
export default function CaloricThresholdNotice({ planTierCalories = 0 }) {
    if (!isBelowMicronutrientEnforcedCalories(planTierCalories)) {
        return null;
    }

    return (
        <div
            role="note"
            className="rounded-[12px] border border-amber-200 bg-amber-50/90 px-4 py-4 sm:px-5"
        >
            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.12em] text-amber-900">
                Please note
            </p>
            <h3 className="mt-2 font-montserrat text-sm font-bold leading-snug text-[#262A22]">
                <span aria-hidden="true">⚠️ </span>
                {CALORIC_THRESHOLD_NOTICE.title}
            </h3>
            <p className="mt-2 font-body text-xs font-medium text-[#5A6B44]">
                Your plan is {planTierCalories} kcal per delivery day — below the{' '}
                {MICRONUTRIENT_ENFORCED_MIN_CALORIES} kcal threshold where Meal Craft optimizes recipes for{' '}
                {ENFORCED_MICRONUTRIENT_TIERS.join(' / ')} kcal micronutrient coverage.
            </p>
            <div className="mt-3 space-y-3">
                {CALORIC_THRESHOLD_NOTICE.paragraphs.map((paragraph) => (
                    <p key={paragraph.slice(0, 48)} className="font-body text-sm leading-relaxed text-[#374151]">
                        {paragraph}
                    </p>
                ))}
            </div>
        </div>
    );
}
