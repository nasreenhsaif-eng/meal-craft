import { isValidElement } from 'react';

/**
 * Inertia v3 layout callbacks receive `{ children, ...pageProps }`.
 * Older layouts used the page element directly. Accept both shapes.
 *
 * @param {import('react').ReactElement | { children?: import('react').ReactNode } | unknown} pageOrProps
 * @returns {import('react').ReactNode}
 */
export function resolveInertiaLayoutChild(pageOrProps) {
    if (isValidElement(pageOrProps)) {
        return pageOrProps;
    }

    if (pageOrProps !== null && typeof pageOrProps === 'object' && 'children' in pageOrProps) {
        return /** @type {{ children?: import('react').ReactNode }} */ (pageOrProps).children;
    }

    return pageOrProps;
}
