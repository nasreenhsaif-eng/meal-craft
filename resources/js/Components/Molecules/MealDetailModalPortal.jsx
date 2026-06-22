import { createPortal } from 'react-dom';
import MealDetailView from './MealDetailView/MealDetailView';

/**
 * @param {{
 *   mealDetailModal: { title: string; detailView: object } | null;
 *   onClose: () => void;
 *   loading?: boolean;
 * }} props
 */
export default function MealDetailModalPortal({ mealDetailModal, onClose, loading = false }) {
    if (!mealDetailModal && !loading) {
        return null;
    }

    return createPortal(
        <div className="fixed inset-0 z-[120] flex items-end justify-center p-0 sm:items-center sm:p-6">
            <button
                type="button"
                className="absolute inset-0 bg-black/40"
                aria-label="Close meal details"
                onClick={onClose}
            />
            <div className="relative flex max-h-[92dvh] w-full max-w-3xl flex-col overflow-hidden rounded-t-[16px] bg-white shadow-2xl sm:rounded-[16px]">
                <div className="flex shrink-0 items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 sm:px-6">
                    <h2 className="min-w-0 flex-1 break-words font-montserrat text-lg font-bold text-[#262A22]">
                        {mealDetailModal?.title ?? 'Meal details'}
                    </h2>
                    <button
                        type="button"
                        className="shrink-0 font-montserrat text-sm font-bold text-[#5A6B44]"
                        onClick={onClose}
                    >
                        Close
                    </button>
                </div>
                {loading ? (
                    <p className="px-5 py-8 text-center font-body text-sm text-[#555555] sm:px-6">Loading meal details…</p>
                ) : mealDetailModal ? (
                    <MealDetailView meal={mealDetailModal.detailView} hideImage={false} embedded />
                ) : null}
            </div>
        </div>,
        document.body,
    );
}
