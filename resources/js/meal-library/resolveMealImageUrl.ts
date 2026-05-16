/**
 * Ensures meal image src values from CSV / API resolve to same-origin URLs under
 * {@code public/images/…}, {@code storage/…}, or absolute http(s) URLs.
 */
function cellLooksLikeImageReference(value: string): boolean {
    if (value === '') {
        return false;
    }

    if (value.includes('/images/') || value.startsWith('images/')) {
        return true;
    }

    if (value.includes('/storage/') || value.startsWith('meals/')) {
        return true;
    }

    if (/^https?:\/\//i.test(value)) {
        return relativePathFromAbsoluteImageUrl(value) !== null || /\.(jpe?g|png|gif|webp|svg)(\?.*)?$/i.test(value);
    }

    return /\.(jpe?g|png|gif|webp|svg)(\?.*)?$/i.test(value);
}

export function stripMarkdownImageLink(raw: string): string {
    const match = raw.match(/\[([^\]]*)\]\(([^)]+)\)/);
    if (!match) {
        return raw;
    }

    const label = match[1]?.trim() ?? '';
    const href = match[2]?.trim() ?? '';

    if (cellLooksLikeImageReference(label)) {
        return label;
    }

    if (cellLooksLikeImageReference(href)) {
        return href;
    }

    return label || href;
}

/** @returns relative stored path, or null for external URLs. */
export function relativePathFromAbsoluteImageUrl(url: string): string | null {
    try {
        const parsed = new URL(url);
        let path = parsed.pathname.replace(/^\/+/, '');

        if (path.startsWith('images/')) {
            return path;
        }

        if (path.startsWith('storage/')) {
            path = path.slice('storage/'.length);
        }

        return path !== '' ? path : null;
    } catch {
        return null;
    }
}

export function resolveMealImageUrl(raw?: string | null): string {
    let value = stripMarkdownImageLink(String(raw ?? '').trim());
    if (value === '') {
        return '';
    }

    if (/^https?:\/\//i.test(value)) {
        const relative = relativePathFromAbsoluteImageUrl(value);
        if (relative) {
            value = relative;
        } else {
            return value;
        }
    }

    if (value.startsWith('//')) {
        if (typeof window !== 'undefined') {
            return `${window.location.protocol}${value}`;
        }

        return `https:${value}`;
    }

    value = value.replace(/^public\/+/i, '').replace(/^\/+/, '');

    if (value.startsWith('storage/')) {
        value = value.slice('storage/'.length);
    }

    if (value.startsWith('/')) {
        if (typeof window !== 'undefined') {
            return `${window.location.origin}${value}`;
        }

        return value;
    }

    if (value.startsWith('images/')) {
        if (typeof window !== 'undefined') {
            return `${window.location.origin}/${value}`;
        }

        return `/${value}`;
    }

    if (value.startsWith('meals/')) {
        const storagePath = `storage/${value}`;
        if (typeof window !== 'undefined') {
            return `${window.location.origin}/${storagePath}`;
        }

        return `/${storagePath}`;
    }

    if (typeof window !== 'undefined') {
        return `${window.location.origin}/${value}`;
    }

    return `/${value}`;
}
