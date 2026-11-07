import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import CustomerCheckoutDetailsReview from '../../Components/CustomerIntake/CustomerCheckoutDetailsReview.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { submitCheckoutDetailsNative } from './submitCheckoutDetailsNative.js';

/**
 * @param {unknown} errors
 * @returns {Record<string, string>}
 */
function flattenErrors(errors) {
    if (!errors || typeof errors !== 'object' || Array.isArray(errors)) {
        return {};
    }

    /** @type {Record<string, string>} */
    const out = {};

    for (const [key, value] of Object.entries(errors)) {
        if (typeof value === 'string' && value.trim() !== '') {
            out[key] = value;
            continue;
        }

        if (Array.isArray(value) && value.length > 0) {
            out[key] = String(value[0] ?? '');
        }
    }

    return out;
}

/**
 * @param {object} props
 * @param {Record<string, mixed>} props.form
 * @param {Record<string, Array<{value: string, label: string}>>} props.options
 * @param {string} [props.uniqueCode]
 * @param {string} [props.intakeSubmissionId]
 * @param {string} [props.customerName]
 * @param {string} [props.submitUrl]
 * @param {string} [props.deliveryUrl]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {boolean} [props.declarationAccepted]
 */
export default function CustomerDetails({
    form,
    options,
    uniqueCode = '',
    intakeSubmissionId = '',
    customerName = '',
    submitUrl = '/checkout/details',
    deliveryUrl = '/checkout/delivery',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    declarationAccepted = false,
}) {
    const page = usePage();
    const flashSuccess = typeof page.props?.flash?.success === 'string' ? page.props.flash.success : null;
    const flashError = typeof page.props?.flash?.error === 'string' ? page.props.flash.error : null;
    const serverErrors = flattenErrors(page.props?.errors);
    const [submitting, setSubmitting] = useState(false);
    const [data, setDataState] = useState(() => ({
        ...form,
        declaration_accepted: declarationAccepted,
    }));

    /**
     * Same call shape as Inertia useForm setData, without Inertia/cloneDeep side effects.
     *
     * @param {string | Record<string, mixed> | ((current: Record<string, mixed>) => Record<string, mixed>)} keyOrData
     * @param {mixed} [maybeValue]
     */
    const setData = (keyOrData, maybeValue) => {
        setDataState((current) => {
            if (typeof keyOrData === 'function') {
                return keyOrData(current);
            }

            if (typeof keyOrData === 'string') {
                return { ...current, [keyOrData]: maybeValue };
            }

            if (keyOrData && typeof keyOrData === 'object') {
                return { ...current, ...keyOrData };
            }

            return current;
        });
    };

    return (
        <CustomerInertiaShell customerName={customerName} footerNav={null}>
            <div className="mx-auto w-full min-w-0">
                <div className="flex items-start justify-between gap-3">
                    <h1 className="min-w-0 font-montserrat text-3xl font-semibold tracking-tight text-[#262A22]">
                        Your details
                    </h1>
                    <Button
                        type="button"
                        label="Close"
                        variant="ghost"
                        size="sm"
                        onClick={() => router.visit(deliveryUrl)}
                        className="shrink-0"
                    />
                </div>
                <p className="mt-3 font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                    Review your onboarding details, complete delivery information, and confirm everything is
                    correct.
                </p>
                {flashSuccess ? (
                    <p className="mt-4 font-body text-sm font-medium text-[#5A6B44]">{flashSuccess}</p>
                ) : null}
                {flashError ? (
                    <p className="mt-4 font-body text-sm font-medium text-status-error" role="alert">
                        {flashError}
                    </p>
                ) : null}
                <div className="mt-6 w-full min-w-0">
                    <CustomerCheckoutDetailsReview
                        data={data}
                        setData={setData}
                        errors={serverErrors}
                        processing={submitting}
                        options={options}
                        uniqueCode={uniqueCode}
                        intakeSubmissionId={intakeSubmissionId}
                        onSubmit={(payload = data) => {
                            if (submitting) {
                                return;
                            }

                            setSubmitting(true);

                            // Classic form POST — full navigation to payment (no SPA blank).
                            void submitCheckoutDetailsNative(submitUrl, payload, '/checkout/payment').catch(
                                (error) => {
                                    // eslint-disable-next-line no-console
                                    console.error(error);
                                    setSubmitting(false);
                                    window.alert(
                                        error instanceof Error
                                            ? error.message
                                            : 'Could not continue to payment. Refresh and try again.',
                                    );
                                },
                            );
                        }}
                    />
                </div>
            </div>
        </CustomerInertiaShell>
    );
}
