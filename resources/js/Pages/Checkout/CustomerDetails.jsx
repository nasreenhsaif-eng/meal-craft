import { Link, router, useForm, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button/Button.jsx';
import CustomerIntakeForm from '../../Components/CustomerIntake/CustomerIntakeForm.jsx';
import CustomerAppHeaderActions from '../../Components/Molecules/Customer/CustomerAppHeaderActions.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';

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
}) {
    const page = usePage();
    const flashSuccess = typeof page.props?.flash?.success === 'string' ? page.props.flash.success : null;
    const { data, setData, post, processing, errors } = useForm({ ...form });

    return (
        <CustomerInertiaShell customerName={customerName} headerActions={<CustomerAppHeaderActions />}>
            <div className="mx-auto w-full min-w-0 max-w-3xl">
                <Button
                    type="button"
                    label="← Back to kitchen"
                    variant="ghost"
                    size="sm"
                    onClick={() => router.visit(deliveryUrl)}
                    className="!h-auto !min-h-0 px-0"
                />
                <h1 className="mt-3 font-montserrat text-3xl font-semibold tracking-tight text-[#262A22]">
                    Your details
                </h1>
                <p className="mt-3 font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                    Confirm how we should reach you, where to deliver, and the plan you want to start.
                </p>
                {flashSuccess ? (
                    <p className="mt-4 font-body text-sm font-medium text-[#5A6B44]">{flashSuccess}</p>
                ) : null}
                <div className="mt-6 w-full min-w-0">
                    <CustomerIntakeForm
                        data={data}
                        setData={setData}
                        errors={errors}
                        processing={processing}
                        options={options}
                        uniqueCode={uniqueCode}
                        intakeSubmissionId={intakeSubmissionId}
                        submitLabel="Save details"
                        onSubmit={() => post(submitUrl)}
                    />
                </div>
                <p className="mt-4 font-body text-xs text-[#6B7280]">
                    You can return to your dashboard anytime from{' '}
                    <Link href={homeUrl} className="font-semibold text-[#5A6B44] hover:underline">
                        Home
                    </Link>
                    .
                </p>
            </div>
        </CustomerInertiaShell>
    );
}

CustomerDetails.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
