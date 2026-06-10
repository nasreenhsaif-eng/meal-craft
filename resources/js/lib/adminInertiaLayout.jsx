import AdminInertiaShell from '../Layouts/AdminInertiaShell.jsx';
import { resolveInertiaLayoutChild } from './resolveInertiaLayoutChild.js';

/**
 * @param {import('react').ReactElement | { children?: import('react').ReactNode }} pageOrProps
 */
export default function adminInertiaLayout(pageOrProps) {
    return <AdminInertiaShell>{resolveInertiaLayoutChild(pageOrProps)}</AdminInertiaShell>;
}
