import { addons } from 'storybook/manager-api';
import { SET_CURRENT_STORY, STORY_MISSING } from 'storybook/internal/core-events';
import { mealCraftTheme } from './mealCraftTheme.js';

addons.setConfig({
    theme: mealCraftTheme,
});

/**
 * Stories moved Design System/01. Foundations → 01. Tokens; remap bookmarked / shared IDs.
 * e.g. design-system-01-foundations-logos-horizontal-lockups--minimal
 *   → design-system-01-tokens-logos-horizontal-lockups--minimal
 */
addons.register('meal-craft/foundations-to-tokens-redirect', () => {
    const channel = addons.getChannel();

    channel.on(STORY_MISSING, (payload) => {
        const missingId = typeof payload === 'string' ? payload : payload?.storyId ?? payload?.id;
        if (typeof missingId !== 'string' || !missingId.startsWith('design-system-01-foundations-')) {
            return;
        }

        const nextId = missingId.replace(
            'design-system-01-foundations-',
            'design-system-01-tokens-',
        );

        if (nextId === missingId) {
            return;
        }

        channel.emit(SET_CURRENT_STORY, { storyId: nextId });
    });
});
