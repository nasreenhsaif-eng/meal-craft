/**
 * Ensures meal image src values from CSV / API resolve to same-origin URLs under
 * {@code public/images/…} or {@code storage/…}, not broken relative paths on nested routes.
 */
export function resolveMealImageUrl(raw?: string | null): string {
    const value = String(raw ?? '').trim();
    if (value === '') {
        return '';
    }

    if (/^https?:\/\//i.test(value)) {
        return value;
    }

    if (value.startsWith('//')) {
        if (typeof window !== 'undefined') {
            return `${window.location.protocol}${value}`;
        }

        return `https:${value}`;
    }

    if (value.startsWith('/')) {
        if (typeof window !== 'undefined') {
            return `${window.location.origin}${value}`;
        }

        return value;
    }

    if (value.startsWith('images/') || value.startsWith('storage/')) {
        if (typeof window !== 'undefined') {
            return `${window.location.origin}/${value}`;
        }

        return `/${value}`;
    }

    return value;
}
