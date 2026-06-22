import { useCallback, useState } from 'react';

/**
 * @param {string} [detailViewUrlTemplate] e.g. `/api/meals/{id}/detail-view`
 */
export function useMealDetailModal(detailViewUrlTemplate = '/api/meals/{id}/detail-view') {
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

            if (meal.detailView) {
                setMealDetailModal({
                    title: meal.title ?? 'Meal details',
                    detailView: meal.detailView,
                });
                return;
            }

            const mealId = meal.id;
            if (!mealId) {
                return;
            }

            const url = detailViewUrlTemplate.replace('{id}', encodeURIComponent(String(mealId)));
            setDetailLoading(true);

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
                        detailView: data.detailView,
                    });
                }
            } catch {
                // ignore network errors — modal stays closed
            } finally {
                setDetailLoading(false);
            }
        },
        [detailViewUrlTemplate],
    );

    return {
        mealDetailModal,
        detailLoading,
        openMealDetail,
        closeMealDetail,
    };
}
