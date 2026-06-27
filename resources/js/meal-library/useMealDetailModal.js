import { useCallback, useState } from 'react';

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

            const baseUrl = detailViewUrlTemplate.replace('{id}', encodeURIComponent(String(mealId)));
            const resolvedQueryString =
                typeof detailQueryString === 'function' ? detailQueryString() : detailQueryString;
            const url = resolvedQueryString ? `${baseUrl}?${resolvedQueryString}` : baseUrl;
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
        [detailViewUrlTemplate, detailQueryString],
    );

    return {
        mealDetailModal,
        detailLoading,
        openMealDetail,
        closeMealDetail,
    };
}
