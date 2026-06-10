import {
    IconBalanced,
    IconCycleSync,
    IconKetobiotic,
    IconSickleCellWarrior,
    IconThyroid,
} from './DietProtocolIcons.jsx';

/**
 * Diet protocol that immediately advances onboarding when selected.
 */
export const AUTO_ADVANCE_DIET_PROTOCOL_ID = 'balanced';

/** @typedef {'balanced' | 'ketobiotic' | 'cycle_sync' | 'thyroid' | 'sickle_cell'} DietProtocolId */

/**
 * @typedef {{
 *   id: DietProtocolId;
 *   label: string;
 *   description: string;
 *   Icon: import('react').ComponentType<{ className?: string }>;
 * }} DietProtocolOption
 */

/** @type {DietProtocolOption[]} */
export const DIET_PROTOCOL_OPTIONS = [
    {
        id: 'balanced',
        label: 'Balanced Protocol',
        description: 'Flexible macros with varied whole foods for everyday wellness.',
        Icon: IconBalanced,
    },
    {
        id: 'ketobiotic',
        label: 'Ketobiotic',
        description:
            'Lower carbs with high-fiber, gut-friendly prebiotic vegetables and clean anti-inflammatory fats.',
        Icon: IconKetobiotic,
    },
    {
        id: 'thyroid',
        label: 'Thyroid Protocol',
        description: 'Iodine-aware, steady-energy nutrition designed to support thyroid health.',
        Icon: IconThyroid,
    },
    {
        id: 'cycle_sync',
        label: 'Cycle Sync nutrition for women',
        description: 'Phase-aware meals aligned with your menstrual cycle.',
        Icon: IconCycleSync,
    },
    {
        id: 'sickle_cell',
        label: 'Sickle cell anemia warrior',
        description: 'Gentle, nutrient-dense plans tailored for sickle cell care.',
        Icon: IconSickleCellWarrior,
    },
];

/**
 * @param {DietProtocolId} protocolId
 * @returns {boolean}
 */
export function shouldAutoAdvanceDietProtocol(protocolId) {
    return protocolId === AUTO_ADVANCE_DIET_PROTOCOL_ID;
}

/**
 * @param {import('../../meal-craft/onboarding/onboardingConstants.js').OnboardingGender | '' | undefined} gender
 * @returns {DietProtocolOption[]}
 */
export function dietProtocolOptionsForGender(gender) {
    if (gender === 'female') {
        return DIET_PROTOCOL_OPTIONS;
    }

    return DIET_PROTOCOL_OPTIONS.filter((option) => option.id !== 'cycle_sync');
}
