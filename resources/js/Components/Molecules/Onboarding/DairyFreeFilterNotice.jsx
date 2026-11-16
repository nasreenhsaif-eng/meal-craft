import { useEffect, useLayoutEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import Button from '../../Atoms/Button/Button.jsx';
import { resolveCustomerPhoneFrameHost } from '../Customer/resolveCustomerPhoneFrameHost.js';

/**
 * Modal guidance when a customer opts to avoid dairy during onboarding.
 * Portals into the customer phone frame when present so it stays inside the chrome.
 *
 * @param {{ onDismiss: () => void }} props
 */
export default function DairyFreeFilterNotice({ onDismiss }) {
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
                aria-label="Dismiss dairy note"
            />
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="dairy-free-filter-notice-title"
                className="relative z-10 flex max-h-full w-full max-w-[min(100%,24rem)] shrink-0 flex-col overflow-hidden rounded-[16px] border border-amber-200 bg-amber-50 shadow-xl"
            >
                <div className="overflow-y-auto overscroll-contain px-4 py-5 sm:px-5">
                    <h3
                        id="dairy-free-filter-notice-title"
                        className="font-montserrat text-sm font-bold text-[#262A22] sm:text-base"
                    >
                        A Note on Going Dairy-Free
                    </h3>
                    <div className="mt-2 space-y-2 font-body text-sm leading-relaxed text-[#374151]">
                        <p>
                            Unless you have a medical condition like lactose intolerance or an allergy, we do
                            not generally advise going completely dairy-free.
                        </p>
                        <p>
                            Dairy provides a unique, highly bioavailable matrix of Calcium, Vitamin K2, Iodine,
                            and B vitamins working together to protect your bones, heart, and thyroid—a
                            combination that is remarkably difficult to replace through other foods alone.
                        </p>
                        <p>
                            If you are avoiding dairy due to quality concerns, rest assured that we only use
                            premium dairy products completely free from preservatives, emulsifiers, and
                            additives.
                        </p>
                        <p>
                            <span className="font-medium text-[#262A22]">Avoiding for health/medical reasons?</span>{' '}
                            Your plan will remain strictly dairy-free.
                        </p>
                        <p>
                            <span className="font-medium text-[#262A22]">Choosing by preference?</span> We highly
                            encourage keeping our clean dairy options for optimal nutrition. If you still wish
                            to exclude it, we will prioritize alternative whole-food sources (like leafy greens,
                            tahini, and sardines) to help bridge the nutritional gap.
                        </p>
                    </div>
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
