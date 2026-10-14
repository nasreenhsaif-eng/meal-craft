/** Shared field shell: fill the parent, never overflow the viewport. */
export const TEXT_INPUT_ROOT = 'block w-full min-w-0 max-w-full text-left';

const VIEWPORT_PAD = 8;
const MENU_GAP = 8;
const MENU_ESTIMATE = 228;

/**
 * Align a portaled menu to a trigger without spilling past the viewport.
 *
 * @param {DOMRect} rect
 * @returns {{ left: number, top: number, width: number }}
 */
export function portalMenuRectFromTrigger(rect) {
    const vw = typeof window === 'undefined' ? rect.width : window.innerWidth;
    const vh = typeof window === 'undefined' ? rect.bottom + MENU_ESTIMATE : window.innerHeight;
    const width = Math.max(0, Math.min(rect.width, vw - VIEWPORT_PAD * 2));
    const maxLeft = Math.max(VIEWPORT_PAD, vw - width - VIEWPORT_PAD);
    const left = Math.min(Math.max(VIEWPORT_PAD, rect.left), maxLeft);
    const spaceBelow = vh - rect.bottom - MENU_GAP - VIEWPORT_PAD;
    const openAbove = spaceBelow < 120 && rect.top > MENU_ESTIMATE;
    const top = openAbove
        ? Math.max(VIEWPORT_PAD, rect.top - MENU_GAP - Math.min(MENU_ESTIMATE, rect.top - VIEWPORT_PAD))
        : rect.bottom + MENU_GAP;

    return { left, top, width };
}
