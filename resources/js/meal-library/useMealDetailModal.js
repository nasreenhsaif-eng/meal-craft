import { useCallback, useState } from 'react';

/**
 * @param {Record<string, unknown> | undefined | null} initial
 * @param {Record<string, unknown>} fromApi
 */
function mergeDetailViews(initial, fromApi) {
    if (!initial || typeof initial !== 'object') {
        return fromApi;
    }

    return {
        ...initial,
        ...fromApi,
        nutritionalData: fromApi.nutritionalData ?? initial.nutritionalData,
        ingredients:
            Array.isArray(fromApi.ingredients) && fromApi.ingredients.length > 0
                ? fromApi.ingredients
                : initial.ingredients,
    };
}

/**
 * @param {Record<string, unknown> | undefined | null} detailView
 */
function hasConsultationDetailView(detailView) {
    return (
        detailView &&
        typeof detailView === 'object' &&
        (detailView.nutritionalData != null || detailView.macros != null)
    );
}

/**
 * @param {string} [detailViewUrlTemplate] e.g. `/api/meals/{id}/detail-view`
 * @param {string | (() => string)} [detailQueryString] Adapted-menu query string (craft, tier, day, etc.). A function is resolved when the modal opens so params stay in sync with the consultation screen.
 */
export function useMealDetailModal(detailViewUrlTemplate = '/api/meals/{id}/detail-view', detailQueryString = '') {
    const [mealDetailModal, setMealDetailModal] = useState(
        /** @type {{ title: string; detailView: object } | null} */ (null),
    );
    const [detailLoading, setDetailLoading] = useState(false);

    const closeMealDetail = useCallback(() => {
        setMealDetailModal(null);
    }, []);

    const openMealDetail = useCallback(
        async (meal) => {
            if (!meal) {
                return;
            }

            const mealId = meal.id;
            if (!mealId) {
                return;
            }

            const initialDetailView = meal.detailView;
            const hasInitialDetail = hasConsultationDetailView(initialDetailView);

            if (hasInitialDetail) {
                setMealDetailModal({
                    title: meal.title ?? 'Meal details',
                    detailView: /** @type {object} */ (initialDetailView),
                });
            }

            const baseUrl = detailViewUrlTemplate.replace('{id}', encodeURIComponent(String(mealId)));
            const resolvedQueryString =
                typeof detailQueryString === 'function' ? detailQueryString() : detailQueryString;
            const url = resolvedQueryString ? `${baseUrl}?${resolvedQueryString}` : baseUrl;
            setDetailLoading(!hasInitialDetail);

            try {
                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();

                if (data?.detailView) {
                    setMealDetailModal({
                        title: meal.title ?? 'Meal details',
                        detailView: mergeDetailViews(
                            hasInitialDetail ? /** @type {Record<string, unknown>} */ (initialDetailView) : null,
                            data.detailView,
                        ),
                    });
                }
            } catch {
                // ignore network errors — keep any on-screen detailView already shown
            } finally {
                setDetailLoading(false);
            }
        },
        [detailViewUrlTemplate, detailQueryString],
    );

    return {
        mealDetailModal,
        detailLoading,
        openMealDetail,
        closeMealDetail,
    };
}
