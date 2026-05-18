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

/** User-facing copy when Laravel returns HTTP 419. */
export const CSRF_SESSION_EXPIRED_MESSAGE =
    'Your session expired. Refresh the page (Cmd+Shift+R), then try the upload again.';
