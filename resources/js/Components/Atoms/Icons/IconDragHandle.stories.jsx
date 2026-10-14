import IconDragHandle from './IconDragHandle.jsx';

export default {
    title: 'Design System/02. Atoms/Button/DragHandle',
    component: IconDragHandle,
    parameters: {
        layout: 'padded',
        canvasBackground: 'white',
    },
};

export const Default = {
    render: () => (
        <div className="bg-white p-8 text-[#364153]">
            <button
                type="button"
                className="inline-flex items-center gap-2 rounded-md px-2 py-1 font-body text-sm text-[#1F2937] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#556C37] focus-visible:ring-offset-2"
                aria-label="Reorder row"
            >
                <IconDragHandle />
                Meal row
            </button>
        </div>
    ),
};
