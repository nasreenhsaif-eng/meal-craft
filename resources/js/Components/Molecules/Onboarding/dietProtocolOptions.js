import {
    IconBalanced,
    IconCycleSync,
    IconKetobiotic,
    IconSickleCellWarrior,
} from './DietProtocolIcons.jsx';

/** @typedef {'balanced' | 'ketobiotic' | 'cycle_sync' | 'sickle_cell'} DietProtocolId */

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
        label: 'Balanced',
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
