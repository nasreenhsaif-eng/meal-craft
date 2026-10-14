import { describe, expect, it } from 'vitest';
import { buildKitchenDailySheetCsv } from './logisticsCsvExport.js';

describe('buildKitchenDailySheetCsv', () => {
    it('inserts Kitchen instructions before Special Requests', () => {
        const csv = buildKitchenDailySheetCsv(
            [
                {
                    id: '1',
                    name: 'Jordan Lee',
                    breakfast: 'Oats',
                    m1: 'Chicken',
                    m2: '',
                    soup: '',
                    sideSalad: '',
                    dessert: '',
                    cutlery: 'Fork',
                    kitchenInstructions:
                        'breakfast: 3 eggs, vegetables: 100g\nmeal 1: chicken 120g, carbs: 180g, vegetables: 50g, sauce: 50g',
                    specialRequests: '',
                    allergies: 'None',
                },
            ],
            '2026-10-14',
            { includeBom: false },
        );

        const [header] = csv.split('\r\n');
        expect(header).toContain('Kitchen instructions');
        expect(header.indexOf('Kitchen instructions')).toBeLessThan(header.indexOf('Special Requests'));
        expect(csv).toContain('breakfast: 3 eggs, vegetables: 100g');
        expect(csv).toContain('meal 1: chicken 120g, carbs: 180g, vegetables: 50g, sauce: 50g');
    });
});
