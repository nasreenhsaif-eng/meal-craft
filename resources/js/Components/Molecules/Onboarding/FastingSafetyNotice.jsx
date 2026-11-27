import { useEffect, useLayoutEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import Button from '../../Atoms/Button/Button.jsx';
import { resolveCustomerPhoneFrameHost } from '../Customer/resolveCustomerPhoneFrameHost.js';
import { FASTING_SAFETY_NOTE } from './cycleSyncPhaseUtils.js';

/**
 * Safety modal when a customer opts into Meals + fasting.
 * Portals into the customer phone frame when present so it stays inside the chrome.
 *
 * @param {{ onDismiss: () => void }} props
 */
export default function FastingSafetyNotice({ onDismiss }) {
    const [host, setHost] = useState(/** @type {HTMLElement | null} */ (null));

    useLayoutEffect(() => {
        setHost(resolveCustomerPhoneFrameHost());
    }, []);

    useEffect(() => {
        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                onDismiss();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('keydown', onKeyDown);
        };
    }, [onDismiss]);

    if (!host) {
        return null;
    }

    const nestedInPhoneFrame = host !== document.body;

    return createPortal(
        <div
            className={
                nestedInPhoneFrame
                    ? 'absolute inset-0 z-[120] flex items-center justify-center overflow-hidden overscroll-none p-3'
                    : 'fixed inset-0 z-[120] flex items-center justify-center p-3 sm:p-4'
            }
        >
            <button
                type="button"
                className="absolute inset-0 bg-black/40"
                onClick={onDismiss}
                aria-label="Dismiss fasting safety note"
            />
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="fasting-safety-notice-title"
                className="relative z-10 flex max-h-full w-full max-w-[min(100%,24rem)] shrink-0 flex-col overflow-hidden rounded-[16px] border border-border-light bg-white shadow-xl"
            >
                <div className="overflow-y-auto overscroll-contain px-4 py-5 sm:px-5">
                    <h3
                        id="fasting-safety-notice-title"
                        className="font-montserrat text-sm font-bold text-heading sm:text-base"
                    >
                        A note on fasting
                    </h3>
                    <p className="mt-2 font-body text-sm leading-relaxed text-[#374151]">
                        {FASTING_SAFETY_NOTE}
                    </p>
                    <div className="mt-4">
                        <Button
                            type="button"
                            label="Got it"
                            size="sm"
                            className="w-full uppercase tracking-[0.08em] sm:w-auto sm:min-w-[140px]"
                            onClick={onDismiss}
                        />
                    </div>
                </div>
            </div>
        </div>,
        host,
    );
}
