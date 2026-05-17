import { arrayMove } from '@dnd-kit/sortable';

type Identifiable = { id: string | number };

/**
 * Reorders items visible in the table while preserving relative positions of non-visible rows in `parentList`.
 */
export function reorderMealsInParentList<T extends Identifiable>(
    parentList: readonly T[],
    visibleList: readonly T[],
    activeId: string,
    overId: string,
): T[] {
    const visibleIds = visibleList.map((m) => String(m.id));
    const oldIndex = visibleIds.indexOf(activeId);
    const newIndex = visibleIds.indexOf(overId);

    if (oldIndex < 0 || newIndex < 0 || oldIndex === newIndex) {
        return [...parentList];
    }

    const reorderedVisible = arrayMove([...visibleList], oldIndex, newIndex);
    const visibleIdSet = new Set(visibleIds);
    const queue = [...reorderedVisible];

    return parentList.map((item) => {
        if (visibleIdSet.has(String(item.id))) {
            return queue.shift() as T;
        }

        return item;
    });
}
