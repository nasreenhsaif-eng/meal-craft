import { describe, expect, it } from 'vitest';
import {
    consultationDessertDeckForDay,
    isGreekYogurtChiaDessertMeal,
} from './ChooseYourMeals.jsx';

/** @param {string} id @param {string} title */
function dessert(id, title) {
    return { id, title, mealType: 'Dessert' };
}

describe('consultationDessertDeckForDay', () => {
    it('includes one Greek yogurt chia option alongside baked desserts', () => {
        const catalog = [
            dessert('brownie', 'Chocolate Orange Brownie'),
            dessert('fruit', 'Fruit Salad Bowl'),
            dessert('chia-blueberry', 'Blueberry Walnut Greek Yogurt Chia Pudding'),
            dessert('chia-raspberry', 'Raspberry Cacao Greek Yogurt Chia Pudding'),
            dessert('muffin', 'Saffron Pumpkin Muffin'),
        ];

        const scheduled = [dessert('brownie', 'Chocolate Orange Brownie'), dessert('fruit', 'Fruit Salad Bowl')];

        const deck = consultationDessertDeckForDay(catalog, scheduled);

        expect(deck).toHaveLength(3);
        expect(deck.map((meal) => meal.id)).toEqual(['brownie', 'fruit', 'chia-blueberry']);
    });

    it('prefers Greek yogurt chia over coconut chia when filling the deck', () => {
        const catalog = [
            dessert('brownie', 'Chocolate Orange Brownie'),
            dessert('fruit', 'Fruit Salad Bowl'),
            dessert('chia-coconut', 'Blueberry Walnut Chia Pudding'),
            dessert('chia-blueberry', 'Blueberry Walnut Greek Yogurt Chia Pudding'),
        ];

        const scheduled = [dessert('brownie', 'Chocolate Orange Brownie'), dessert('fruit', 'Fruit Salad Bowl')];

        const deck = consultationDessertDeckForDay(catalog, scheduled);

        expect(deck.map((meal) => meal.id)).toEqual(['brownie', 'fruit', 'chia-blueberry']);
    });

    it('does not add extra chia desserts when one is already scheduled', () => {
        const catalog = [
            dessert('brownie', 'Chocolate Orange Brownie'),
            dessert('chia-blueberry', 'Blueberry Walnut Greek Yogurt Chia Pudding'),
            dessert('chia-raspberry', 'Raspberry Cacao Greek Yogurt Chia Pudding'),
        ];

        const scheduled = [dessert('chia-blueberry', 'Blueberry Walnut Greek Yogurt Chia Pudding')];

        const deck = consultationDessertDeckForDay(catalog, scheduled);

        expect(deck.map((meal) => meal.id)).toEqual(['chia-blueberry', 'brownie']);
    });

    it('keeps TBD Weekly Protocol desserts to baked + fruit only (chia is breakfast)', () => {
        const catalog = [
            dessert('brownie', 'Chocolate Orange Brownie'),
            dessert('fruit', 'Fruit Salad Bowl'),
            dessert('chia-blueberry', 'Blueberry Walnut Greek Yogurt Chia Pudding'),
            dessert('muffin', 'Saffron Pumpkin Muffin'),
        ];

        const scheduled = [
            dessert('brownie', 'Chocolate Orange Brownie'),
            dessert('fruit', 'Fruit Salad Bowl'),
            dessert('chia-blueberry', 'Blueberry Walnut Greek Yogurt Chia Pudding'),
        ];

        const deck = consultationDessertDeckForDay(catalog, scheduled, { preferBakedDesserts: true });

        expect(deck).toHaveLength(2);
        expect(deck.map((meal) => meal.id)).toEqual(['brownie', 'fruit']);
        expect(deck.some((meal) => isGreekYogurtChiaDessertMeal(meal))).toBe(false);
    });
});

describe('isGreekYogurtChiaDessertMeal', () => {
    it('matches Greek yogurt chia pudding titles', () => {
        expect(
            isGreekYogurtChiaDessertMeal({
                title: 'Blueberry Walnut Greek Yogurt Chia Pudding',
            }),
        ).toBe(true);
    });
});
