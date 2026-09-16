import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import Button from '../Atoms/Button.jsx';

/**
 * SEE OTHER OPTIONS dialog — carousel of alternative plates over the day overview.
 *
 * @param {object} props
 * @param {string} [props.dayLabel] e.g. "SUNDAY"
 * @param {string} [props.sectionTitle] e.g. "Main Meals"
 * @param {number} [props.optionCount]
 * @param {number} [props.selectedCount]
 * @param {number} [props.maxSelected]
 * @param {() => void} props.onBack
 * @param {() => void} [props.onConfirm] Defaults to onBack when omitted.
 * @param {import('react').ReactNode} [props.children]
 * @param {string} [props.className]
 */
export default function ProtocolMealOptionsScreen({
    dayLabel = '',
    sectionTitle = 'Meals',
    optionCount = 0,
    selectedCount = 0,
    maxSelected = 1,
    onBack,
    onConfirm,
    children,
    className = '',
}) {
    const handleConfirm = typeof onConfirm === 'function' ? onConfirm : onBack;
    const handleClose = typeof onBack === 'function' ? onBack : handleConfirm;
    const day = String(dayLabel).trim().toUpperCase();

    useEffect(() => {
        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                handleClose?.();
            }
        };

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [handleClose]);

    if (typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <div className="fixed inset-0 z-[110] flex items-end justify-center p-0 sm:items-center sm:p-6">
            <button
                type="button"
                className="absolute inset-0 bg-black/40"
                aria-label="Close other options"
                onClick={handleClose}
            />
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="mc-options-modal-title"
                className={[
                    'relative flex max-h-[92dvh] w-full max-w-5xl flex-col overflow-hidden',
                    'rounded-t-[16px] bg-[#F8F9F6] shadow-2xl sm:rounded-[16px]',
                    className,
                ]
                    .join(' ')
                    .trim()}
            >
                <div className="flex shrink-0 items-start justify-between gap-3 border-b border-gray-200 px-4 py-3 sm:px-5 sm:py-4">
                    <div className="min-w-0 flex-1">
                        {day ? (
                            <p className="font-montserrat text-[13px] font-bold uppercase tracking-wide text-[#262A22]">
                                CRAFTING YOUR {day}
                            </p>
                        ) : null}
                        <h2
                            id="mc-options-modal-title"
                            className="mt-1 font-montserrat text-xl font-bold tracking-tight text-[#262A22] sm:text-2xl"
                        >
                            {sectionTitle}
                        </h2>
                        <p className="mt-0.5 font-body text-sm italic text-[#555555]">
                            {optionCount} options · {selectedCount}/{maxSelected} selected
                        </p>
                    </div>
                    <button
                        type="button"
                        className="shrink-0 font-montserrat text-sm font-bold text-[#5A6B44]"
                        onClick={handleClose}
                    >
                        Close
                    </button>
                </div>

                <div className="min-h-0 flex-1 overflow-x-clip overflow-y-auto px-0 py-3 sm:py-4">
                    {children}
                </div>

                <div className="shrink-0 border-t border-gray-200 bg-white p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    <div className="flex items-center justify-between gap-3">
                        <Button
                            type="button"
                            label="BACK"
                            variant="outline"
                            size="md"
                            onClick={handleClose}
                            className="shrink-0"
                        />
                        <Button
                            type="button"
                            label="CONFIRM"
                            variant="primary"
                            size="md"
                            onClick={handleConfirm}
                            className="shrink-0"
                        />
                    </div>
                </div>
            </div>
        </div>,
        document.body,
    );
}
