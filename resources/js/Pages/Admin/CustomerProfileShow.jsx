import { Link, useForm, usePage } from '@inertiajs/react';
import CustomerIntakeForm from '../../Components/CustomerIntake/CustomerIntakeForm.jsx';
import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';

/**
 * @param {object} props
 * @param {Record<string, mixed>} props.form
 * @param {Record<string, Array<{value: string, label: string}>>} props.options
 * @param {string} [props.uniqueCode]
 * @param {string} [props.intakeSubmissionId]
 * @param {string} [props.customerName]
 * @param {string} [props.submitUrl]
 * @param {string} [props.listUrl]
 */
export default function CustomerProfileShow({
    form,
    options,
    uniqueCode = '',
    intakeSubmissionId = '',
    customerName = '',
    submitUrl,
    listUrl = '/admin/customers',
}) {
    const page = usePage();
    const flashSuccess = typeof page.props?.flash?.success === 'string' ? page.props.flash.success : null;
    const { data, setData, put, processing, errors } = useForm({ ...form });

    return (
        <div className="space-y-6">
            <div>
                <Link
                    href={listUrl}
                    className="inline-flex items-center gap-1 font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                >
                    ← Customer Profiles
                </Link>
                <h1 className="mt-3 font-montserrat text-2xl font-semibold text-[#262A22]">
                    {customerName || 'Customer profile'}
                </h1>
                <p className="mt-2 text-sm text-[#555555]">
                    View and edit intake details collected at onboarding and checkout.
                </p>
                {flashSuccess ? (
                    <p className="mt-3 font-body text-sm font-medium text-[#5A6B44]">{flashSuccess}</p>
                ) : null}
            </div>

            <CustomerIntakeForm
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                options={options}
                uniqueCode={uniqueCode}
                intakeSubmissionId={intakeSubmissionId}
                submitLabel="Save profile"
                onSubmit={() => put(submitUrl)}
            />
        </div>
    );
}

CustomerProfileShow.layout = adminInertiaLayout;
