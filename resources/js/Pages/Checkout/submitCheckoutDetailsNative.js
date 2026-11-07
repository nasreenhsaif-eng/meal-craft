import { resolveCsrfToken } from '../../lib/csrfToken.js';

export const CHECKOUT_FIELD_KEYS = [
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
 * Native document POST to checkout details (full browser navigation).
 *
 * @param {string} submitUrl
 * @param {Record<string, mixed>} data
 * @param {string} [_paymentUrl]
 * @returns {Promise<void>}
 */
export async function submitCheckoutDetailsNative(submitUrl, data, _paymentUrl = '/checkout/payment') {
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

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = submitUrl;
    form.acceptCharset = 'UTF-8';
    form.style.display = 'none';
    form.setAttribute('data-checkout-details-native', '1');

    /**
     * @param {string} name
     * @param {string} value
     */
    const appendField = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    };

    appendField('_token', token);

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
                appendField(key, '1');
            }

            continue;
        }

        appendField(key, String(value));
    }

    document.body.appendChild(form);
    // Synchronous native submit — full document navigation to payment.
    HTMLFormElement.prototype.submit.call(form);
}
