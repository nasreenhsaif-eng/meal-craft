import { useId } from 'react';
import Button from '../Atoms/Button/Button.jsx';
import SquareCheckbox from '../Atoms/Icons/SquareCheckbox.jsx';
import TextInput from '../Atoms/TextInput/TextInput.jsx';
import CalendarDateField from '../Molecules/Calendar/CalendarDateField.jsx';
import FoodFilterPill from '../MealSystem/FoodFilterPill.jsx';

/**
 * @typedef {{ value: string; label: string }} IntakeOption
 */

/**
 * @param {string | null | undefined} iso
 * @returns {string}
 */
function formatDisplayDate(iso) {
    if (!iso || typeof iso !== 'string') {
        return '—';
    }

    const [year, month, day] = iso.split('-');
    if (!year || !month || !day) {
        return iso;
    }

    return `${day}/${month}/${year}`;
}

/**
 * @param {string} value
 * @param {IntakeOption[]} options
 * @returns {string}
 */
function labelFor(value, options = []) {
    if (!value) {
        return '—';
    }

    const match = options.find((option) => option.value === String(value));

    return match?.label ?? value;
}

/**
 * @param {{ title: string; children: import('react').ReactNode; action?: import('react').ReactNode }} props
 */
function Section({ title, children, action = null }) {
    return (
        <section className="rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <h2 className="m-0 font-montserrat text-base font-bold tracking-tight text-[#262A22]">{title}</h2>
                {action}
            </div>
            <div className="mt-4">{children}</div>
        </section>
    );
}

/**
 * @param {{ rows: Array<{ label: string; value: string }> }} props
 */
function DefinitionList({ rows }) {
    return (
        <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {rows.map((row) => (
                <div key={row.label} className="min-w-0 rounded-[12px] bg-[#F8F9F6] px-4 py-3">
                    <dt className="font-montserrat text-xs font-bold uppercase tracking-wide text-[#555555]">
                        {row.label}
                    </dt>
                    <dd className="mt-1 break-words font-body text-sm font-medium text-[#262A22]">{row.value}</dd>
                </div>
            ))}
        </dl>
    );
}

/**
 * Customer checkout review: onboarding summary (read-only) + editable delivery + declaration.
 *
 * @param {{
 *   data: Record<string, mixed>;
 *   setData: (key: string, value: mixed) => void;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   options?: Record<string, IntakeOption[]>;
 *   uniqueCode?: string;
 *   intakeSubmissionId?: string;
 *   profileEditUrl?: string;
 *   phoneEditable?: boolean;
 *   declarationAccepted?: boolean;
 *   onSubmit: () => void;
 * }} props
 */
export default function CustomerCheckoutDetailsReview({
    data,
    setData,
    errors = {},
    processing = false,
    options = {},
    uniqueCode = '',
    intakeSubmissionId = '',
    profileEditUrl = '/onboarding/gender',
    phoneEditable = false,
    onSubmit,
}) {
    const declarationId = useId();
    const fieldClass = 'w-full !max-w-full';
    const declarationChecked = Boolean(data.declaration_accepted);

    const contactRows = [
        {
            label: 'Name',
            value: [data.first_name, data.last_name].filter(Boolean).join(' ') || '—',
        },
        { label: 'Email', value: data.email || '—' },
        ...(!phoneEditable ? [{ label: 'Phone', value: data.phone || '—' }] : []),
        {
            label: 'Contact preference',
            value: labelFor(data.contact_preference, options.contactPreferences),
        },
    ];

    const bodyPlanRows = [
        { label: 'Date of birth', value: formatDisplayDate(data.date_of_birth) },
        { label: 'Gender', value: labelFor(data.gender, options.genders) },
        { label: 'Height (cm)', value: data.height_cm || '—' },
        { label: 'Weight (kg)', value: data.weight_kg || '—' },
        { label: 'Target weight (kg)', value: data.target_weight_kg || '—' },
        { label: 'Activity level', value: labelFor(data.activity_level, options.activityLevels) },
        { label: 'Type of plan', value: labelFor(data.plan_type, options.planTypes) },
        { label: 'Days', value: labelFor(String(data.plan_days ?? ''), options.planDays) },
        { label: 'Diet protocol', value: labelFor(data.diet_protocol, options.dietProtocols) },
        {
            label: 'Dislikes & allergies',
            value: data.dislikes_and_allergies || '—',
        },
    ];

    return (
        <form
            className="flex w-full min-w-0 flex-col gap-5"
            onSubmit={(event) => {
                event.preventDefault();
                if (!declarationChecked || processing) {
                    return;
                }
                onSubmit();
            }}
        >
            <Section
                title="Contact"
                action={
                    <Button
                        type="button"
                        label="Edit profile"
                        variant="ghost"
                        size="sm"
                        onClick={() => window.location.assign(profileEditUrl)}
                        className="!h-auto !min-h-0 shrink-0 px-0"
                    />
                }
            >
                <DefinitionList rows={contactRows} />
                {phoneEditable ? (
                    <div className="mt-4">
                        <TextInput
                            label="Phone number"
                            type="tel"
                            value={data.phone ?? ''}
                            onChange={(event) => setData('phone', event.target.value)}
                            error={errors.phone}
                            autoComplete="tel"
                            className={fieldClass}
                        />
                    </div>
                ) : null}
            </Section>

            <Section
                title="Body & plan"
                action={
                    <Button
                        type="button"
                        label="Edit profile"
                        variant="ghost"
                        size="sm"
                        onClick={() => window.location.assign(profileEditUrl)}
                        className="!h-auto !min-h-0 shrink-0 px-0"
                    />
                }
            >
                <DefinitionList rows={bodyPlanRows} />
            </Section>

            <Section title="Delivery address">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 [&>*]:min-w-0">
                    <div className="md:col-span-2">
                        <p className="mb-2 font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94">
                            Delivery time
                        </p>
                        <div className="flex flex-wrap gap-2" role="group" aria-label="Delivery time">
                            {(options.deliveryTimes ?? []).map((option) => (
                                <FoodFilterPill
                                    key={option.value}
                                    label={option.label}
                                    isActive={(data.delivery_time ?? '') === option.value}
                                    onClick={() => setData('delivery_time', option.value)}
                                />
                            ))}
                        </div>
                        {errors.delivery_time ? (
                            <p className="mt-1.5 text-sm text-status-error" role="alert">
                                {errors.delivery_time}
                            </p>
                        ) : null}
                    </div>
                    <CalendarDateField
                        label="Planned starting date"
                        value={data.planned_start_date ?? ''}
                        onChange={(iso) => setData('planned_start_date', iso)}
                        error={errors.planned_start_date}
                        className={fieldClass}
                    />
                    <TextInput
                        label="Area"
                        value={data.area ?? ''}
                        onChange={(event) => setData('area', event.target.value)}
                        error={errors.area}
                        className={fieldClass}
                    />
                    <TextInput
                        label="Block"
                        value={data.block ?? ''}
                        onChange={(event) => setData('block', event.target.value)}
                        error={errors.block}
                        className={fieldClass}
                    />
                    <TextInput
                        label="Road"
                        value={data.road ?? ''}
                        onChange={(event) => setData('road', event.target.value)}
                        error={errors.road}
                        className={fieldClass}
                    />
                    <TextInput
                        label="House number"
                        value={data.house_number ?? ''}
                        onChange={(event) => setData('house_number', event.target.value)}
                        error={errors.house_number}
                        className={fieldClass}
                    />
                    <TextInput
                        label="Gate / flat number"
                        value={data.gate_flat_number ?? ''}
                        onChange={(event) => setData('gate_flat_number', event.target.value)}
                        error={errors.gate_flat_number}
                        className={fieldClass}
                    />
                    <TextInput
                        label="Country"
                        value={data.country ?? ''}
                        onChange={(event) => setData('country', event.target.value)}
                        error={errors.country}
                        className={fieldClass}
                    />
                </div>
            </Section>

            <Section title="Confirm">
                <div className="space-y-4">
                    <label
                        htmlFor={declarationId}
                        className="group/item flex cursor-pointer select-none items-start gap-3"
                    >
                        <input
                            id={declarationId}
                            type="checkbox"
                            checked={declarationChecked}
                            onChange={(event) => setData('declaration_accepted', event.target.checked)}
                            className="peer sr-only"
                        />
                        <span className="mt-0.5 inline-flex shrink-0 rounded-[4px] peer-focus-visible:ring-2 peer-focus-visible:ring-[#556C37] peer-focus-visible:ring-offset-2">
                            <SquareCheckbox presentational checked={declarationChecked} />
                        </span>
                        <span className="font-montserrat text-sm font-bold uppercase leading-snug tracking-wide text-[#262A22]">
                            I declare that all the information that was provided is true and to the best of my
                            knowledge
                        </span>
                    </label>
                    {errors.declaration_accepted ? (
                        <p className="text-sm text-status-error" role="alert">
                            {errors.declaration_accepted}
                        </p>
                    ) : null}

                    <ul className="space-y-2 font-body text-xs leading-relaxed text-[#555555] sm:text-sm">
                        <li>
                            Meal Craft only facilitates meal delivery. It is not responsible for Picnic kitchen
                            cross-contamination or allergy handling.
                        </li>
                        <li>
                            Meal Craft is a technology company only and is not responsible for anything related to
                            the food, including preparation, quality, safety, or delivery execution by the kitchen
                            partner.
                        </li>
                        <li>
                            This is not medical advice. Nutrition information uses technology and data derived from
                            the FDA database and calculated estimates.
                        </li>
                    </ul>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <TextInput
                            label="Unique ID"
                            value={uniqueCode}
                            onChange={() => {}}
                            readOnly
                            disabled
                            className={fieldClass}
                        />
                        <TextInput
                            label="Submission ID"
                            value={intakeSubmissionId || 'Assigned on first confirm'}
                            onChange={() => {}}
                            readOnly
                            disabled
                            className={fieldClass}
                        />
                    </div>
                </div>
            </Section>

            <div className="flex w-full justify-center pt-1">
                <Button
                    type="submit"
                    label={processing ? 'Confirming…' : 'Confirm details'}
                    disabled={processing || !declarationChecked}
                    className="w-full min-w-[200px] max-w-sm uppercase tracking-[0.08em]"
                />
            </div>
        </form>
    );
}
