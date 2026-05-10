import SafetyAlerts from '../MealSystem/SafetyAlerts.jsx';
import PreferenceTags from '../MealSystem/PreferenceTags.jsx';
import { partitionSafetyNotes } from './parseSafetyNotes.js';

const EMPTY = <span className="text-sm font-medium text-[#9CA3AF]">—</span>;

/**
 * @param {{ kitchenPrint?: boolean }} props
 */
function cellShellClass(kitchenPrint) {
    if (kitchenPrint) {
        return 'min-w-0 max-w-[18rem] whitespace-normal break-words px-2 py-2 align-top print:max-w-none print:px-1 print:py-1';
    }
    return 'min-w-[8rem] max-w-[18rem] whitespace-normal break-words px-3 py-2 align-top';
}

/**
 * @param {{ allergyNotes?: string; allergies?: string; specialRequests?: string; kitchenPrint?: boolean; className?: string }} props
 */
export function AllergyTagGroupCell({ allergyNotes, allergies, specialRequests, kitchenPrint = false, className = '' }) {
    const { allergies: list } = partitionSafetyNotes({ allergyNotes, allergies, specialRequests });
    const shell = `${cellShellClass(kitchenPrint)} ${className}`.trim();
    const wrap = kitchenPrint ? 'flex flex-wrap gap-1.5 content-start print:gap-1' : 'flex flex-wrap gap-1.5 content-start';

    if (list.length === 0) {
        return <td className={shell}>{EMPTY}</td>;
    }

    return (
        <td className={shell}>
            <div className={wrap}>
                <SafetyAlerts alerts={list.map((label) => ({ label, variant: 'allergy' }))} />
            </div>
        </td>
    );
}

/**
 * @param {{ allergyNotes?: string; allergies?: string; specialRequests?: string; kitchenPrint?: boolean; className?: string }} props
 */
export function DislikeTagGroupCell({ allergyNotes, allergies, specialRequests, kitchenPrint = false, className = '' }) {
    const { dislikes } = partitionSafetyNotes({ allergyNotes, allergies, specialRequests });
    const shell = `${cellShellClass(kitchenPrint)} ${className}`.trim();
    const wrap = kitchenPrint ? 'flex flex-wrap gap-1.5 content-start print:gap-1' : 'flex flex-wrap gap-1.5 content-start';

    if (dislikes.length === 0) {
        return <td className={shell}>{EMPTY}</td>;
    }

    return (
        <td className={shell}>
            <div className={wrap}>
                <PreferenceTags tags={dislikes} />
            </div>
        </td>
    );
}
