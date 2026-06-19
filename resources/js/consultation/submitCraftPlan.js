/**
 * @param {{
 *   craftKey: string;
 *   weekDuration: number;
 *   selectedDays: number[];
 *   selectedByDay: Record<number, {
 *     breakfasts: string[];
 *     meals: string[];
 *     sideSalads: string[];
 *     desserts: string[];
 *     soup: string[];
 *   }>;
 * }} input
 */
export function buildCraftPlanSubmissionPayload(input) {
    const days = input.selectedDays.map((day) => {
        const selections = input.selectedByDay[day] ?? {
            breakfasts: [],
            meals: [],
            sideSalads: [],
            desserts: [],
            soup: [],
        };

        return {
            day_of_week: day,
            include_soup: selections.soup.length > 0,
            selections: {
                breakfasts: selections.breakfasts.map((id) => Number.parseInt(String(id), 10)).filter((id) => id > 0),
                meals: selections.meals.map((id) => Number.parseInt(String(id), 10)).filter((id) => id > 0),
                sideSalads: selections.sideSalads.map((id) => Number.parseInt(String(id), 10)).filter((id) => id > 0),
                desserts: selections.desserts.map((id) => Number.parseInt(String(id), 10)).filter((id) => id > 0),
                soup: selections.soup.map((id) => Number.parseInt(String(id), 10)).filter((id) => id > 0),
            },
        };
    });

    return {
        craft_key: input.craftKey,
        week_duration: input.weekDuration,
        selected_days: input.selectedDays,
        days,
    };
}

import { CSRF_SESSION_EXPIRED_MESSAGE, resolveCsrfToken, resolveXsrfHeaderToken } from '../lib/csrfToken.js';

/**
 * @param {Record<string, unknown>} payload
 * @param {string} [url]
 * @param {string} [csrfFallback]
 * @returns {Promise<{ message?: string; summary_url?: string; plan?: Record<string, unknown> }>}
 */
export async function submitCraftPlan(payload, url = '/api/customer/craft-plan', csrfFallback = '') {
    const plainToken = resolveCsrfToken(csrfFallback);
    const xsrfToken = resolveXsrfHeaderToken();

    /** @type {Record<string, string>} */
    const headers = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (plainToken !== '') {
        headers['X-CSRF-TOKEN'] = plainToken;
    }

    if (xsrfToken !== '') {
        headers['X-XSRF-TOKEN'] = xsrfToken;
    }

    const response = await fetch(url, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        if (response.status === 419) {
            throw new Error(CSRF_SESSION_EXPIRED_MESSAGE);
        }

        if (response.status === 401) {
            throw new Error('Your session expired. Refresh the page and log in again to save your plan.');
        }

        const message = typeof body.message === 'string' ? body.message : 'Could not save craft plan.';
        throw new Error(message);
    }

    return body;
}
