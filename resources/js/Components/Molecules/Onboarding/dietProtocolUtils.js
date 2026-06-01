import { DIET_PROTOCOL_OPTIONS } from './dietProtocolOptions.js';

export const DIET_PROTOCOL_IDS = DIET_PROTOCOL_OPTIONS.map((option) => option.id);

/** @returns {import('./dietProtocolOptions.js').DietProtocolId} */
export function defaultDietProtocol() {
    return 'balanced';
}

/**
 * @param {string | null | undefined} value
 * @returns {import('./dietProtocolOptions.js').DietProtocolId}
 */
export function resolveDietProtocol(value) {
    const storeToUi = {
        ketogenic: 'ketobiotic',
        sickle_cell_warrior: 'sickle_cell',
    };

    const candidate = typeof value === 'string' ? (storeToUi[value] ?? value) : value;

    if (typeof candidate === 'string' && DIET_PROTOCOL_IDS.includes(candidate)) {
        return /** @type {import('./dietProtocolOptions.js').DietProtocolId} */ (candidate);
    }

    return defaultDietProtocol();
}

/**
 * @param {import('./dietProtocolOptions.js').DietProtocolId} value
 * @returns {string}
 */
export function dietProtocolLabel(value) {
    return DIET_PROTOCOL_OPTIONS.find((option) => option.id === value)?.label ?? value;
}

/**
 * @param {import('./dietProtocolOptions.js').DietProtocolId} value
 * @returns {string}
 */
export function dietProtocolDescription(value) {
    return DIET_PROTOCOL_OPTIONS.find((option) => option.id === value)?.description ?? '';
}
