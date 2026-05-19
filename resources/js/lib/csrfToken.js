/**
 * Laravel keeps the session CSRF token in the `XSRF-TOKEN` cookie (updated on every response).
 * The `<meta name="csrf-token">` tag is only set on the initial full page load and goes stale after Inertia visits.
 *
 * @returns {string|null}
 */
export function readXsrfTokenFromCookie() {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    if (!match?.[1]) {
        return null;
    }

    try {
        return decodeURIComponent(match[1]);
    } catch {
        return match[1];
    }
}

/**
 * @param {string} [fallback]
 * @returns {string}
 */
export function resolveCsrfToken(fallback = '') {
    const fromCookie = readXsrfTokenFromCookie();
    if (fromCookie) {
        return fromCookie;
    }

    const fromMeta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')?.trim();
    if (fromMeta) {
        return fromMeta;
    }

    if (typeof fallback === 'string' && fallback.trim() !== '') {
        return fallback.trim();
    }

    return '';
}

/**
 * Keep the meta tag aligned with Inertia shared props after client-side navigations.
 *
 * @param {string} token
 */
export function syncCsrfMetaTag(token) {
    if (typeof document === 'undefined') {
        return;
    }

    const trimmed = typeof token === 'string' ? token.trim() : '';
    if (trimmed === '') {
        return;
    }

    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', trimmed);
}

/**
 * @param {import('axios').AxiosStatic} axiosInstance
 */
export function configureLaravelAxios(axiosInstance) {
    axiosInstance.defaults.withCredentials = true;
    axiosInstance.defaults.xsrfCookieName = 'XSRF-TOKEN';
    axiosInstance.defaults.xsrfHeaderName = 'X-CSRF-TOKEN';
    axiosInstance.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    axiosInstance.defaults.headers.common.Accept = 'application/json';
}

/**
 * @param {unknown} error
 */
export function isCsrfMismatchError(error) {
    if (!error || typeof error !== 'object' || !('response' in error)) {
        return false;
    }

    const response = /** @type {{ status?: number; data?: { message?: unknown } }} */ (error).response;
    if (response?.status === 419) {
        return true;
    }

    const message = response?.data?.message;

    return typeof message === 'string' && /csrf/i.test(message);
}

/** User-facing copy when Laravel rejects a CSRF token (HTTP 419 or equivalent message). */
export const CSRF_SESSION_EXPIRED_MESSAGE =
    'Your session expired. Refresh the page (Cmd+Shift+R), then try the upload again.';

/**
 * @param {string} [fallback]
 * @returns {Record<string, string>}
 */
export function laravelAxiosJsonHeaders(fallback = '') {
    const token = resolveCsrfToken(fallback);

    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
    };
}

/**
 * @param {FormData} formData
 * @param {string} [fallback]
 * @returns {string} Resolved token (may be empty).
 */
export function appendCsrfToFormData(formData, fallback = '') {
    const token = resolveCsrfToken(fallback);

    if (token !== '' && !formData.has('_token')) {
        formData.append('_token', token);
    }

    return token;
}
