import { resolveCsrfToken, resolveXsrfHeaderToken } from '../../lib/csrfToken.js';

const CHECKOUT_FIELD_KEYS = [
    'declaration_accepted',
    'phone',
    'contact_preference',
    'delivery_time',
    'area',
    'block',
    'road',
    'house_number',
    'gate_flat_number',
    'country',
    'planned_start_date',
    'follow_instagram',
    'customer_question',
    'uncalculated_plan',
];

/**
 * Save checkout details then hard-navigate to payment (avoids blank SPA transitions).
 *
 * @param {string} submitUrl
 * @param {Record<string, mixed>} data
 * @param {string} [paymentUrl]
 * @returns {Promise<void>}
 */
export async function submitCheckoutDetailsNative(
    submitUrl,
    data,
    paymentUrl = '/checkout/payment',
) {
    if (typeof document === 'undefined') {
        throw new Error('Document is not available.');
    }

    const token = resolveCsrfToken();

    if (!token) {
        throw new Error('Missing CSRF token. Refresh the page and try again.');
    }

    if (!data?.declaration_accepted) {
        throw new Error('Please accept the declaration to continue.');
    }

    const body = new URLSearchParams();
    body.set('_token', token);

    for (const key of CHECKOUT_FIELD_KEYS) {
        if (!Object.prototype.hasOwnProperty.call(data, key)) {
            continue;
        }

        const value = data[key];

        if (value === undefined || value === null) {
            continue;
        }

        if (typeof value === 'boolean') {
            if (value) {
                body.set(key, '1');
            }

            continue;
        }

        body.set(key, String(value));
    }

    const headers = {
        'Content-Type': 'application/x-www-form-urlencoded',
        Accept: 'text/html,application/xhtml+xml',
        'X-CSRF-TOKEN': token,
    };

    const xsrf = resolveXsrfHeaderToken();

    if (xsrf) {
        headers['X-XSRF-TOKEN'] = xsrf;
    }

    const response = await fetch(submitUrl, {
        method: 'POST',
        headers,
        body,
        credentials: 'same-origin',
        redirect: 'follow',
    });

    // Always hard-navigate so payment mounts in a clean document (no stale React root).
    if (response.ok || response.redirected) {
        const next =
            typeof response.url === 'string' && response.url.includes('/checkout/payment')
                ? response.url
                : paymentUrl;
        window.location.assign(next);
        return;
    }

    if (response.status === 419) {
        throw new Error('Your session expired. Refresh the page and try again.');
    }

    if (response.status === 422) {
        window.location.assign('/checkout/details');
        return;
    }

    // Fall through: still try payment if details were saved; otherwise reload details.
    window.location.assign(paymentUrl);
}
