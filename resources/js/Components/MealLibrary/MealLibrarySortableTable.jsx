import { useMemo } from 'react';
import {
    DndContext,
    KeyboardSensor,
    PointerSensor,
    closestCenter,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import MealListRow from '../MealListRow.jsx';
import SquareCheckbox from '../Atoms/Icons/SquareCheckbox.jsx';
import { reorderMealsInParentList } from '../../meal-library/reorderMealRows.ts';

/**
 * @param {{
 *   meal: object;
 *   selected: boolean;
 *   onToggleSelected: () => void;
 * }} props
 */
function SortableMealListRow({ meal, selected, onToggleSelected }) {
    const id = String(meal.id);
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <MealListRow
            ref={setNodeRef}
            style={style}
            meal={meal}
            selected={selected}
            onToggleSelected={onToggleSelected}
            isDragging={isDragging}
            dragHandleProps={{ ...attributes, ...listeners }}
        />
    );
}

/**
 * Admin meal library list table with drag-and-drop row reordering.
 *
 * @param {{
 *   displayedMeals: object[];
 *   mealRows: object[];
 *   selectedSet: Set<string>;
 *   allVisibleSelected: boolean;
 *   onToggleAllVisible: () => void;
 *   onToggleRow: (id: string) => void;
 *   onRowReorder: (updatedMeals: object[]) => void;
 * }} props
 */
export default function MealLibrarySortableTable({
    displayedMeals,
    mealRows,
    selectedSet,
    allVisibleSelected,
    onToggleAllVisible,
    onToggleRow,
    onRowReorder,
}) {
    const sortableIds = useMemo(() => displayedMeals.map((m) => String(m.id)), [displayedMeals]);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    function handleDragEnd(event) {
        const { active, over } = event;
        if (!over || active.id === over.id) {
            return;
        }

        const next = reorderMealsInParentList(
            mealRows,
            displayedMeals,
            String(active.id),
            String(over.id),
        );
        onRowReorder(next);
    }

    return (
        <div className="overflow-x-auto">
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                <table className="min-w-[640px] w-full border-collapse text-[#1F2937]">
                    <thead className="bg-white">
                        <tr className="border-b border-gray-200">
                            <th className="w-9 px-2 py-3" aria-hidden="true">
                                <span className="sr-only">Reorder</span>
                            </th>
                            <th className="w-[52px] px-3 py-3 text-left">
                                <button
                                    type="button"
                                    onClick={onToggleAllVisible}
                                    aria-label={allVisibleSelected ? 'Deselect all visible' : 'Select all visible'}
                                    className="inline-flex items-center rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                                >
                                    <SquareCheckbox checked={allVisibleSelected} />
                                </button>
                            </th>
                            <th className="min-w-0 px-4 py-3 text-left">
                                <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                    Name
                                </span>
                            </th>
                            <th className="w-[120px] px-3 py-3 text-left">
                                <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                    Type
                                </span>
                            </th>
                            <th className="w-[100px] px-3 py-3 text-right">
                                <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                    Calories
                                </span>
                            </th>
                            <th className="min-w-[180px] px-3 py-3 text-left">
                                <span className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                    Macros
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <SortableContext items={sortableIds} strategy={verticalListSortingStrategy}>
                        <tbody>
                            {displayedMeals.map((meal) => (
                                <SortableMealListRow
                                    key={meal.id}
                                    meal={meal}
                                    selected={selectedSet.has(String(meal.id))}
                                    onToggleSelected={() => onToggleRow(String(meal.id))}
                                />
                            ))}
                        </tbody>
                    </SortableContext>
                </table>
            </DndContext>
        </div>
    );
}
