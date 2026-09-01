import { createPortal } from 'react-dom';
import { type ReactElement, useCallback, useEffect, useState } from 'react';
import MealDetailView, {
    type MealDetailModel,
    type MealIngredientItem,
} from '../MealDetailView/MealDetailView';

export type BaseRecipeDetailModalState = {
    ingredientId: number;
    title: string;
} | null;

type BaseRecipeDetailModalProps = {
    modal: BaseRecipeDetailModalState;
    onClose: () => void;
    onBaseRecipeClick?: (item: MealIngredientItem) => void;
};

export default function BaseRecipeDetailModal({
    modal,
    onClose,
    onBaseRecipeClick,
}: BaseRecipeDetailModalProps): ReactElement | null {
    const [detailView, setDetailView] = useState<MealDetailModel | null>(null);
    const [resolvedTitle, setResolvedTitle] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const loadDetailView = useCallback(async (ingredientId: number) => {
        setLoading(true);
        setError(null);
        setDetailView(null);

        try {
            const response = await fetch(`/api/ingredients/${ingredientId}/detail-view`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Could not load base recipe details.');
            }

            const payload = (await response.json()) as { detailView?: MealDetailModel; title?: string };
            setDetailView(payload.detailView ?? null);
            setResolvedTitle(String(payload.title ?? '').trim());
        } catch (loadError) {
            setError(loadError instanceof Error ? loadError.message : 'Could not load base recipe details.');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (modal?.ingredientId) {
            setResolvedTitle(modal.title);
            void loadDetailView(modal.ingredientId);
        } else {
            setDetailView(null);
            setResolvedTitle('');
            setError(null);
            setLoading(false);
        }
    }, [loadDetailView, modal?.ingredientId, modal?.title]);

    if (!modal) {
        return null;
    }

    const handleNestedBaseRecipeClick = (item: MealIngredientItem) => {
        if (onBaseRecipeClick) {
            onBaseRecipeClick(item);

            return;
        }

        if (item.ingredientId) {
            void loadDetailView(item.ingredientId);
        }
    };

    return createPortal(
        <div className="fixed inset-0 z-[130] flex items-end justify-center p-0 sm:items-center sm:p-6">
            <button
                type="button"
                className="absolute inset-0 bg-black/40"
                aria-label="Close base recipe details"
                onClick={onClose}
            />
            <div className="relative flex max-h-[92dvh] w-full max-w-3xl flex-col overflow-hidden rounded-t-[16px] bg-white shadow-2xl sm:rounded-[16px]">
                <div className="flex shrink-0 items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 sm:px-6">
                    <div className="min-w-0 flex-1">
                        <h2 className="break-words font-montserrat text-lg font-bold text-[#262A22]">
                            {resolvedTitle || modal.title}
                        </h2>
                        {detailView?.shortDescription ? (
                            <p className="mt-1 font-montserrat text-sm font-medium leading-snug text-[#555555]">
                                {detailView.shortDescription}
                            </p>
                        ) : null}
                    </div>
                    <button
                        type="button"
                        className="shrink-0 font-montserrat text-sm font-bold text-[#5A6B44]"
                        onClick={onClose}
                    >
                        Close
                    </button>
                </div>
                {loading ? (
                    <p className="px-5 py-8 text-center font-body text-sm text-[#555555] sm:px-6">
                        Loading base recipe…
                    </p>
                ) : error ? (
                    <p className="px-5 py-8 text-center font-body text-sm text-[#991B1B] sm:px-6">{error}</p>
                ) : detailView ? (
                    <MealDetailView
                        meal={detailView}
                        hideImage
                        embedded
                        onBaseRecipeClick={handleNestedBaseRecipeClick}
                    />
                ) : null}
            </div>
        </div>,
        document.body,
    );
}
