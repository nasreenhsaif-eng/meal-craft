/**
 * Laravel CSRF: plain session token (meta / Inertia props) vs encrypted XSRF-TOKEN cookie.
 * Never send the cookie value as X-CSRF-TOKEN — only as X-XSRF-TOKEN.
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
 * Plain CSRF token for X-CSRF-TOKEN and `_token` form fields.
 *
 * @param {string} [fallback]
 * @returns {string}
 */
export function resolveCsrfToken(fallback = '') {
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
 * Encrypted cookie value for X-XSRF-TOKEN (Laravel decrypts this header).
 *
 * @returns {string}
 */
export function resolveXsrfHeaderToken() {
    return readXsrfTokenFromCookie() ?? '';
}

/**
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
 * Attach CSRF headers to non-GET Inertia visits.
 *
 * @param {import('@inertiajs/core').Router} router
 */
export function configureLaravelInertia(router) {
    router.on('before', (event) => {
        const visit = event.detail.visit;
        const method = (visit.method ?? 'get').toLowerCase();

        if (method === 'get' || method === 'head') {
            return;
        }

        const plainToken = resolveCsrfToken(
            typeof visit.headers?.['X-CSRF-TOKEN'] === 'string' ? visit.headers['X-CSRF-TOKEN'] : '',
        );
        const xsrfToken = resolveXsrfHeaderToken();

        visit.headers = {
            ...visit.headers,
            ...(plainToken !== '' ? { 'X-CSRF-TOKEN': plainToken } : {}),
            ...(xsrfToken !== '' ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
        };

        if (plainToken === '') {
            return;
        }

        const data = visit.data;

        if (data instanceof FormData) {
            if (!data.has('_token')) {
                data.append('_token', plainToken);
            }

            return;
        }

        if (data && typeof data === 'object') {
            visit.data = { ...data, _token: plainToken };
        }
    });
}

/**
 * @param {import('axios').AxiosStatic} axiosInstance
 */
export function configureLaravelAxios(axiosInstance) {
    axiosInstance.defaults.withCredentials = true;
    axiosInstance.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    axiosInstance.defaults.headers.common.Accept = 'application/json';

    axiosInstance.interceptors.request.use((config) => {
        const method = (config.method ?? 'get').toLowerCase();

        if (method === 'get' || method === 'head') {
            return config;
        }

        const plainToken = resolveCsrfToken();
        const xsrfToken = resolveXsrfHeaderToken();

        if (plainToken !== '') {
            config.headers.set('X-CSRF-TOKEN', plainToken);
        }

        if (xsrfToken !== '') {
            config.headers.set('X-XSRF-TOKEN', xsrfToken);
        }

        return config;
    });
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
    'Your session expired. Refresh the page (Cmd+Shift+R), then try again.';

/**
 * JSON headers for standalone axios calls. CSRF is applied in {@link configureLaravelAxios}.
 *
 * @param {string} [_fallback] Unused; kept for call-site compatibility.
 * @returns {Record<string, string>}
 */
export function laravelAxiosJsonHeaders(_fallback = '') {
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
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
