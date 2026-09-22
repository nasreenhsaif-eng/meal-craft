import { useEffect } from 'react';
import { createPortal } from 'react-dom';
import Button from '../Atoms/Button.jsx';

/**
 * SEE OTHER OPTIONS dialog — carousel of alternative plates over the day overview.
 *
 * @param {object} props
 * @param {string} [props.dayLabel] e.g. "SUNDAY"
 * @param {string} [props.sectionTitle] e.g. "Main Meals"
 * @param {() => void} props.onBack
 * @param {() => void} [props.onConfirm] Defaults to onBack when omitted.
 * @param {import('react').ReactNode} [props.children]
 * @param {string} [props.className]
 */
export default function ProtocolMealOptionsScreen({
    dayLabel = '',
    sectionTitle = 'Meals',
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
        <div className="fixed inset-0 z-[110] flex items-end justify-center p-0 pt-14 sm:items-center sm:p-6">
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
                    'relative flex max-h-[calc(100dvh-3.5rem)] w-full max-w-5xl flex-col overflow-hidden sm:max-h-[92dvh]',
                    'rounded-t-[16px] bg-[#F8F9F6] shadow-2xl sm:rounded-[16px]',
                    className,
                ]
                    .join(' ')
                    .trim()}
            >
                <div className="flex shrink-0 items-center justify-between gap-3 border-b border-gray-200 px-4 py-2 sm:px-5 sm:py-2.5">
                    <div className="min-w-0 flex-1">
                        {day ? (
                            <p className="font-montserrat text-[12px] font-bold uppercase tracking-wide text-[#555555]">
                                CRAFTING YOUR {day}
                            </p>
                        ) : null}
                        <h2
                            id="mc-options-modal-title"
                            className="font-montserrat text-lg font-bold tracking-tight text-[#262A22] sm:text-xl"
                        >
                            {sectionTitle}
                        </h2>
                    </div>
                    <Button
                        type="button"
                        label="Close"
                        variant="ghost"
                        size="sm"
                        onClick={handleClose}
                        className="shrink-0"
                    />
                </div>

                <div className="min-h-0 flex-1 overflow-x-clip overflow-y-auto px-0 py-0 sm:py-1">
                    {children}
                </div>

                <div className="shrink-0 border-t border-gray-200 bg-white p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:p-4 sm:pb-[max(1rem,env(safe-area-inset-bottom))]">
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
