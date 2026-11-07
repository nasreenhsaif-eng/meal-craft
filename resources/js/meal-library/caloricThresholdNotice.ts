/** Highest daily calorie total that still shows the caloric threshold notice. */
export const CALORIC_THRESHOLD_NOTICE_MAX_CALORIES = 1200;

export const CALORIC_THRESHOLD_NOTICE = {
    title: 'Nutritional Notice: Caloric Thresholds & Metabolic Safety',
    paragraphs: [
        'Consistently consuming 1,200 calories per day or fewer makes it structurally difficult to achieve the daily Recommended Dietary Allowance (RDA) for essential vitamins and minerals through whole foods alone. Aggressive caloric restriction acts as a systemic stressor, elevating cortisol levels which can increase inflammation and cause temporary fluid retention.',
        'Prolonged low-calorie intake directly suppresses thyroid function, specifically downregulating the conversion of inactive T4 into active T3 hormone. This reduction in active thyroid hormones triggers adaptive thermogenesis — a natural defensive metabolic slowdown where the body intentionally reduces its daily energy expenditure and alters hunger signals.',
        'This hormonal downregulation can compromise lean muscle tissue and make long-term weight management highly unsustainable.',
    ],
};

/**
 * @param {number} planTierCalories
 */
export function isBelowMicronutrientEnforcedCalories(planTierCalories: number): boolean {
    const tier = Math.round(planTierCalories);

    return tier > 0 && tier <= CALORIC_THRESHOLD_NOTICE_MAX_CALORIES;
}
