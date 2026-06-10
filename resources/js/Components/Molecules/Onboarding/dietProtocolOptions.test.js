import { describe, expect, it } from 'vitest';
import {
    AUTO_ADVANCE_DIET_PROTOCOL_ID,
    dietProtocolOptionsForGender,
    shouldAutoAdvanceDietProtocol,
} from './dietProtocolOptions.js';

describe('dietProtocolOptionsForGender', () => {
    it('includes cycle sync for female customers', () => {
        const options = dietProtocolOptionsForGender('female');

        expect(options.some((option) => option.id === 'cycle_sync')).toBe(true);
        expect(options.some((option) => option.id === 'thyroid')).toBe(true);
    });

    it('hides cycle sync for male customers', () => {
        const options = dietProtocolOptionsForGender('male');

        expect(options.some((option) => option.id === 'cycle_sync')).toBe(false);
        expect(options.some((option) => option.id === 'thyroid')).toBe(true);
        expect(options).toHaveLength(4);
    });
});

describe('shouldAutoAdvanceDietProtocol', () => {
    it('only auto-advances balanced protocol', () => {
        expect(shouldAutoAdvanceDietProtocol(AUTO_ADVANCE_DIET_PROTOCOL_ID)).toBe(true);
        expect(shouldAutoAdvanceDietProtocol('ketobiotic')).toBe(false);
        expect(shouldAutoAdvanceDietProtocol('thyroid')).toBe(false);
        expect(shouldAutoAdvanceDietProtocol('cycle_sync')).toBe(false);
    });
});
