/**
 * Ensures meal image src values from CSV / API resolve to same-origin URLs under
 * {@code public/images/…}, {@code storage/…}, or absolute http(s) URLs.
 */
const PUBLIC_MEALS_DIR = 'images/meals';

function hasImageExtension(path: string): boolean {
    return /\.(jpe?g|png|gif|webp|svg)$/i.test(path);
}

function encodePathSegments(path: string): string {
    return path
        .split('/')
        .filter((segment) => segment.length > 0)
        .map((segment) => encodeURIComponent(segment))
        .join('/');
}

function encodeAbsoluteUrlPath(url: string): string {
    try {
        const parsed = new URL(url);
        const segments = parsed.pathname.split('/').filter((segment) => segment.length > 0);
        parsed.pathname = `/${segments.map((segment) => encodeURIComponent(segment)).join('/')}`;
        return parsed.toString();
    } catch {
        return url;
    }
}

function ensurePrefixedRelativePath(path: string): string {
    const trimmed = path.replace(/^\/+/, '');
    if (trimmed.startsWith('images/') || trimmed.startsWith('meals/')) {
        return trimmed;
    }
    if (hasImageExtension(trimmed)) {
        const filename = trimmed.includes('/') ? trimmed.split('/').pop() ?? trimmed : trimmed;
        return `${PUBLIC_MEALS_DIR}/${filename}`;
    }
    return trimmed;
}

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
        return relativePathFromAbsoluteImageUrl(value) !== null || hasImageExtension(value);
    }

    return hasImageExtension(value);
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
        let path = decodeURIComponent(parsed.pathname).replace(/^\/+/, '');

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

function buildOriginUrl(relativePath: string): string {
    const encoded = encodePathSegments(relativePath);
    if (typeof window !== 'undefined') {
        return `${window.location.origin}/${encoded}`;
    }

    return `/${encoded}`;
}

export function resolveMealImageUrl(raw?: string | null): string {
    let value = stripMarkdownImageLink(String(raw ?? '').trim());
    if (value === '') {
        return '';
    }

    if (/^https?:\/\//i.test(value)) {
        const relative = relativePathFromAbsoluteImageUrl(value);
        if (relative) {
            value = ensurePrefixedRelativePath(relative);
        } else {
            return encodeAbsoluteUrlPath(value);
        }
    }

    if (value.startsWith('//')) {
        if (typeof window !== 'undefined') {
            return encodeAbsoluteUrlPath(`${window.location.protocol}${value}`);
        }

        return encodeAbsoluteUrlPath(`https:${value}`);
    }

    value = value.replace(/^public\/+/i, '').replace(/^\/+/, '');

    if (value.startsWith('storage/')) {
        value = value.slice('storage/'.length);
    }

    value = ensurePrefixedRelativePath(value);

    if (value.startsWith('images/')) {
        return buildOriginUrl(value);
    }

    if (value.startsWith('meals/')) {
        return buildOriginUrl(`storage/${value}`);
    }

    return buildOriginUrl(value);
}
