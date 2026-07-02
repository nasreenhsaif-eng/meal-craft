import { describe, expect, it } from 'vitest';
import { consultationSideSaladDeckForDay } from './ChooseYourMeals.jsx';

/** @param {string} id @param {string} title */
function sideSalad(id, title) {
    return { id, title, mealType: 'Side salad' };
}

describe('consultationSideSaladDeckForDay', () => {
    it('shows up to three side salad options', () => {
        const catalog = [
            sideSalad('a', 'Kimchi Purslane Side Salad'),
            sideSalad('b', 'Tahini Purslane Pepper Salad'),
            sideSalad('c', 'Sauerkraut & Rocca Salad'),
            sideSalad('d', 'Citrus Beet Arugula Salad'),
        ];

        const scheduled = [sideSalad('a', 'Kimchi Purslane Side Salad')];

        const deck = consultationSideSaladDeckForDay(catalog, scheduled);

        expect(deck).toHaveLength(3);
        expect(deck.map((meal) => meal.id)).toEqual(['a', 'b', 'c']);
    });
});
