import { useEffect, useId, useMemo, useRef, useState } from 'react';
import Button from '../Atoms/Button/Button.jsx';
import SquareCheckbox from '../Atoms/Icons/SquareCheckbox.jsx';
import DropdownTextInput from '../Atoms/TextInput/DropdownTextInput.jsx';
import TextInput from '../Atoms/TextInput/TextInput.jsx';
import CalendarDateField from '../Molecules/Calendar/CalendarDateField.jsx';
import FoodFilterPill from '../MealSystem/FoodFilterPill.jsx';

/**
 * @typedef {{ value: string; label: string }} IntakeOption
 */

const CONTACT_FIELDS = /** @type {const} */ (['phone', 'contact_preference']);
const DELIVERY_FIELDS = /** @type {const} */ ([
    'delivery_time',
    'planned_start_date',
    'area',
    'block',
    'road',
    'house_number',
    'country',
]);
const CONFIRM_FIELDS = /** @type {const} */ (['declaration_accepted']);

/** Common dial codes — Bahrain first (signup default). */
const PHONE_COUNTRY_CODES = ['+973', '+966', '+971', '+965', '+974', '+968', '+961', '+20', '+44', '+1'];

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
 * @param {unknown} value
 * @returns {boolean}
 */
function isMissing(value) {
    if (typeof value === 'boolean') {
        return !value;
    }

    return value == null || String(value).trim() === '';
}

/**
 * @param {string | null | undefined} phone
 * @returns {{ countryCode: string; nationalNumber: string }}
 */
function splitPhone(phone) {
    const trimmed = String(phone ?? '').trim();

    if (trimmed === '') {
        return { countryCode: '+973', nationalNumber: '' };
    }

    const digitsWithPlus = trimmed.startsWith('+')
        ? `+${trimmed.slice(1).replace(/\D/g, '')}`
        : `+${trimmed.replace(/\D/g, '')}`;

    const codes = [...PHONE_COUNTRY_CODES].sort((a, b) => b.length - a.length);

    for (const code of codes) {
        if (digitsWithPlus.startsWith(code)) {
            return {
                countryCode: code,
                nationalNumber: digitsWithPlus.slice(code.length),
            };
        }
    }

    return {
        countryCode: '+973',
        nationalNumber: digitsWithPlus.replace(/^\+/, ''),
    };
}

/**
 * @param {string} countryCode
 * @param {string} nationalNumber
 * @returns {string}
 */
function combinePhone(countryCode, nationalNumber) {
    const code = String(countryCode || '+973').startsWith('+')
        ? String(countryCode || '+973')
        : `+${String(countryCode || '973').replace(/\D/g, '')}`;
    const national = String(nationalNumber ?? '').replace(/\D/g, '');

    if (national === '') {
        return '';
    }

    return `${code}${national}`;
}

/**
 * @param {string | null | undefined} phone
 * @returns {string}
 */
function formatPhoneDisplay(phone) {
    if (isMissing(phone)) {
        return '—';
    }

    const { countryCode, nationalNumber } = splitPhone(phone);

    if (!nationalNumber) {
        return String(phone);
    }

    return `${countryCode} ${nationalNumber}`;
}

/**
 * @param {Record<string, mixed>} data
 * @returns {Record<string, string>}
 */
function buildClientFieldErrors(data) {
    /** @type {Record<string, string>} */
    const fieldErrors = {};

    if (isMissing(data.phone)) {
        fieldErrors.phone = 'Phone number is required.';
    }

    if (isMissing(data.contact_preference)) {
        fieldErrors.contact_preference = 'Select a contact preference.';
    }

    if (isMissing(data.delivery_time)) {
        fieldErrors.delivery_time = 'Select a delivery time.';
    }

    if (isMissing(data.planned_start_date)) {
        fieldErrors.planned_start_date = 'Planned starting date is required.';
    }

    if (isMissing(data.area)) {
        fieldErrors.area = 'Area is required.';
    }

    if (isMissing(data.block)) {
        fieldErrors.block = 'Block is required.';
    }

    if (isMissing(data.road)) {
        fieldErrors.road = 'Road is required.';
    }

    if (isMissing(data.house_number)) {
        fieldErrors.house_number = 'House number is required.';
    }

    if (isMissing(data.country)) {
        fieldErrors.country = 'Country is required.';
    }

    if (!data.declaration_accepted) {
        fieldErrors.declaration_accepted = 'Please accept the declaration to continue.';
    }

    return fieldErrors;
}

/**
 * @param {Record<string, string>} fieldErrors
 * @param {readonly string[]} keys
 * @returns {boolean}
 */
function sectionHasErrors(fieldErrors, keys) {
    return keys.some((key) => Boolean(fieldErrors[key]));
}

/**
 * Plain underlined text action — no ghost/pill background.
 *
 * @param {{ label?: string; onClick: () => void }} props
 */
function UnderlineEditButton({ label = 'Edit', onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="shrink-0 appearance-none border-0 bg-transparent p-0 font-montserrat text-sm font-semibold text-[#5A6B44] underline decoration-[#5A6B44]/40 underline-offset-2 outline-none transition-colors hover:text-[#485636] hover:decoration-[#5A6B44] focus-visible:rounded-[4px] focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
        >
            {label}
        </button>
    );
}

/**
 * @param {{
 *   title: string;
 *   children: import('react').ReactNode;
 *   action?: import('react').ReactNode;
 *   invalid?: boolean;
 *   errorMessage?: string | null;
 *   sectionRef?: import('react').RefObject<HTMLElement | null>;
 * }} props
 */
function Section({ title, children, action = null, invalid = false, errorMessage = null, sectionRef = null }) {
    return (
        <section
            ref={sectionRef}
            className={[
                'rounded-[12px] border bg-white p-5 shadow-sm sm:p-6',
                invalid ? 'border-status-error' : 'border-gray-200',
            ].join(' ')}
            aria-invalid={invalid ? 'true' : undefined}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <h2
                    className={[
                        'm-0 font-montserrat text-base font-bold tracking-tight',
                        invalid ? 'text-status-error' : 'text-[#262A22]',
                    ].join(' ')}
                >
                    {title}
                </h2>
                {action}
            </div>
            {invalid && errorMessage ? (
                <p className="mt-2 font-body text-sm text-status-error" role="alert">
                    {errorMessage}
                </p>
            ) : null}
            <div className="mt-4">{children}</div>
        </section>
    );
}

/**
 * Scroll the first incomplete section into view (with a little top offset for the header).
 *
 * @param {HTMLElement | null | undefined} element
 */
function scrollToSection(element) {
    if (!element) {
        return;
    }

    const top = element.getBoundingClientRect().top + window.scrollY - 24;
    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
}

/**
 * @param {{ rows: Array<{ label: string; value: string; invalid?: boolean }> }} props
 */
function DefinitionList({ rows }) {
    return (
        <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {rows.map((row) => (
                <div
                    key={row.label}
                    className={[
                        'min-w-0 rounded-[12px] px-4 py-3',
                        row.invalid ? 'bg-status-error/10 ring-1 ring-status-error' : 'bg-[#F8F9F6]',
                    ].join(' ')}
                >
                    <dt
                        className={[
                            'font-montserrat text-xs font-bold uppercase tracking-wide',
                            row.invalid ? 'text-status-error' : 'text-[#555555]',
                        ].join(' ')}
                    >
                        {row.label}
                    </dt>
                    <dd
                        className={[
                            'mt-1 break-words font-body text-sm font-medium',
                            row.invalid ? 'text-status-error' : 'text-[#262A22]',
                        ].join(' ')}
                    >
                        {row.value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

/**
 * @param {{
 *   label: string;
 *   value: string;
 *   options?: IntakeOption[];
 *   onChange: (value: string) => void;
 *   error?: string;
 * }} props
 */
function OptionPillGroup({ label, value, options = [], onChange, error }) {
    return (
        <div className="w-full min-w-0">
            <p
                className={[
                    'mb-2 font-montserrat text-sm font-bold leading-snug tracking-tight',
                    error ? 'text-status-error' : 'text-grey-94',
                ].join(' ')}
            >
                {label}
            </p>
            <div className="flex flex-wrap gap-2" role="group" aria-label={label}>
                {options.map((option) => (
                    <FoodFilterPill
                        key={option.value}
                        label={option.label}
                        isActive={value === option.value}
                        onClick={() => onChange(option.value)}
                    />
                ))}
            </div>
            {error ? (
                <p className="mt-1.5 text-sm text-status-error" role="alert">
                    {error}
                </p>
            ) : null}
        </div>
    );
}

/**
 * Country code + national number fields that write a combined E.164 `phone` value.
 *
 * @param {{
 *   phone: string;
 *   onPhoneChange: (phone: string) => void;
 *   error?: string;
 * }} props
 */
function PhoneNumberFields({ phone, onPhoneChange, error }) {
    const split = useMemo(() => splitPhone(phone), [phone]);
    const [countryCode, setCountryCode] = useState(split.countryCode);
    const [nationalNumber, setNationalNumber] = useState(split.nationalNumber);

    useEffect(() => {
        setCountryCode(split.countryCode);
        setNationalNumber(split.nationalNumber);
    }, [split.countryCode, split.nationalNumber]);

    /**
     * @param {string} nextCode
     * @param {string} nextNational
     */
    const commit = (nextCode, nextNational) => {
        setCountryCode(nextCode);
        setNationalNumber(nextNational);
        onPhoneChange(combinePhone(nextCode, nextNational));
    };

    return (
        <div className="w-full min-w-0">
            <p
                className={[
                    'mb-2 font-montserrat text-sm font-bold leading-snug tracking-tight',
                    error ? 'text-status-error' : 'text-grey-94',
                ].join(' ')}
            >
                Phone number
            </p>
            <div className="grid grid-cols-[7.5rem_minmax(0,1fr)] gap-2 sm:grid-cols-[8.5rem_minmax(0,1fr)] sm:gap-3">
                <DropdownTextInput
                    label="Country code"
                    hideLabel
                    listboxAriaLabel="Country code"
                    value={countryCode}
                    options={PHONE_COUNTRY_CODES}
                    onChange={(nextCode) => commit(nextCode, nationalNumber)}
                    className="!max-w-none"
                />
                <TextInput
                    label="Mobile number"
                    className="!max-w-none [&_label]:sr-only"
                    type="tel"
                    inputMode="tel"
                    placeholder="XXXX XXXX"
                    value={nationalNumber}
                    onChange={(event) => commit(countryCode, event.target.value)}
                    autoComplete="tel-national"
                />
            </div>
            {error ? (
                <p className="mt-1.5 text-sm text-status-error" role="alert">
                    {error}
                </p>
            ) : null}
        </div>
    );
}

/**
 * @param {Record<string, mixed>} data
 * @returns {boolean}
 */
function isDeliveryIncomplete(data) {
    return DELIVERY_FIELDS.some((key) => isMissing(data[key]));
}

/**
 * Customer checkout review: onboarding summary (read-only) + editable contact/delivery + declaration.
 *
 * @param {{
 *   data: Record<string, mixed>;
 *   setData: (key: string, value: mixed) => void;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   options?: Record<string, IntakeOption[]>;
 *   uniqueCode?: string;
 *   intakeSubmissionId?: string;
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
    onSubmit,
}) {
    const declarationId = useId();
    const fieldClass = 'w-full !max-w-full';
    const declarationChecked = Boolean(data.declaration_accepted);
    const phoneMissing = isMissing(data.phone);
    const contactPreferenceMissing = isMissing(data.contact_preference);
    const contactSectionRef = useRef(/** @type {HTMLElement | null} */ (null));
    const deliverySectionRef = useRef(/** @type {HTMLElement | null} */ (null));
    const confirmSectionRef = useRef(/** @type {HTMLElement | null} */ (null));

    const [editingContact, setEditingContact] = useState(
        () => phoneMissing || contactPreferenceMissing,
    );
    const [editingDelivery, setEditingDelivery] = useState(() => isDeliveryIncomplete(data));
    const [showValidation, setShowValidation] = useState(false);

    const clientErrors = useMemo(() => buildClientFieldErrors(data), [data]);
    const fieldErrors = useMemo(() => {
        if (!showValidation && Object.keys(errors).length === 0) {
            return {};
        }

        return { ...clientErrors, ...errors };
    }, [clientErrors, errors, showValidation]);

    const contactInvalid = sectionHasErrors(fieldErrors, CONTACT_FIELDS);
    const deliveryInvalid = sectionHasErrors(fieldErrors, DELIVERY_FIELDS);
    const confirmInvalid = sectionHasErrors(fieldErrors, CONFIRM_FIELDS);

    /**
     * @param {Record<string, string>} nextErrors
     */
    const focusFirstIncompleteSection = (nextErrors) => {
        if (sectionHasErrors(nextErrors, CONTACT_FIELDS)) {
            setEditingContact(true);
            requestAnimationFrame(() => scrollToSection(contactSectionRef.current));
            return;
        }

        if (sectionHasErrors(nextErrors, DELIVERY_FIELDS)) {
            setEditingDelivery(true);
            requestAnimationFrame(() => scrollToSection(deliverySectionRef.current));
            return;
        }

        if (sectionHasErrors(nextErrors, CONFIRM_FIELDS)) {
            requestAnimationFrame(() => scrollToSection(confirmSectionRef.current));
        }
    };

    useEffect(() => {
        if (Object.keys(errors).length === 0) {
            return;
        }

        setShowValidation(true);
        focusFirstIncompleteSection(errors);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only react to new server errors
    }, [errors]);

    const contactRows = [
        {
            label: 'Name',
            value: [data.first_name, data.last_name].filter(Boolean).join(' ') || '—',
        },
        { label: 'Email', value: data.email || '—' },
        {
            label: 'Phone',
            value: formatPhoneDisplay(data.phone),
            invalid: Boolean(fieldErrors.phone),
        },
        {
            label: 'Contact preference',
            value: labelFor(data.contact_preference, options.contactPreferences),
            invalid: Boolean(fieldErrors.contact_preference),
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

    const deliveryRows = [
        {
            label: 'Delivery time',
            value: labelFor(data.delivery_time, options.deliveryTimes),
            invalid: Boolean(fieldErrors.delivery_time),
        },
        {
            label: 'Planned starting date',
            value: formatDisplayDate(data.planned_start_date),
            invalid: Boolean(fieldErrors.planned_start_date),
        },
        { label: 'Area', value: data.area || '—', invalid: Boolean(fieldErrors.area) },
        { label: 'Block', value: data.block || '—', invalid: Boolean(fieldErrors.block) },
        { label: 'Road', value: data.road || '—', invalid: Boolean(fieldErrors.road) },
        {
            label: 'House number',
            value: data.house_number || '—',
            invalid: Boolean(fieldErrors.house_number),
        },
        { label: 'Gate / flat number', value: data.gate_flat_number || '—' },
        { label: 'Country', value: data.country || '—', invalid: Boolean(fieldErrors.country) },
    ];

    return (
        <form
            className="flex w-full min-w-0 flex-col gap-5"
            onSubmit={(event) => {
                event.preventDefault();
                if (processing) {
                    return;
                }

                const nextErrors = buildClientFieldErrors(data);
                const hasClientErrors = Object.keys(nextErrors).length > 0;

                setShowValidation(true);

                if (hasClientErrors) {
                    focusFirstIncompleteSection(nextErrors);
                    return;
                }

                onSubmit();
            }}
            noValidate
        >
            <Section
                title="Contact"
                sectionRef={contactSectionRef}
                invalid={contactInvalid}
                errorMessage={contactInvalid ? 'Complete the missing contact details.' : null}
                action={
                    <UnderlineEditButton
                        onClick={() => setEditingContact((open) => !open)}
                    />
                }
            >
                {editingContact ? (
                    <div className="flex w-full min-w-0 flex-col gap-4">
                        <DefinitionList
                            rows={[
                                {
                                    label: 'Name',
                                    value:
                                        [data.first_name, data.last_name].filter(Boolean).join(' ') ||
                                        '—',
                                },
                                { label: 'Email', value: data.email || '—' },
                            ]}
                        />
                        <PhoneNumberFields
                            phone={String(data.phone ?? '')}
                            onPhoneChange={(nextPhone) => setData('phone', nextPhone)}
                            error={fieldErrors.phone}
                        />
                        <OptionPillGroup
                            label="Contact preference"
                            value={data.contact_preference ?? ''}
                            options={options.contactPreferences}
                            onChange={(value) => setData('contact_preference', value)}
                            error={fieldErrors.contact_preference}
                        />
                    </div>
                ) : (
                    <DefinitionList rows={contactRows} />
                )}
            </Section>

            <Section title="Plan & Biometrics">
                <DefinitionList rows={bodyPlanRows} />
            </Section>

            <Section
                title="Delivery address"
                sectionRef={deliverySectionRef}
                invalid={deliveryInvalid}
                errorMessage={deliveryInvalid ? 'Complete the missing delivery details.' : null}
                action={
                    <UnderlineEditButton
                        onClick={() => setEditingDelivery((open) => !open)}
                    />
                }
            >
                {editingDelivery ? (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 [&>*]:min-w-0">
                        <div className="md:col-span-2">
                            <p
                                className={[
                                    'mb-2 font-montserrat text-sm font-bold leading-snug tracking-tight',
                                    fieldErrors.delivery_time ? 'text-status-error' : 'text-grey-94',
                                ].join(' ')}
                            >
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
                            {fieldErrors.delivery_time ? (
                                <p className="mt-1.5 text-sm text-status-error" role="alert">
                                    {fieldErrors.delivery_time}
                                </p>
                            ) : null}
                        </div>
                        <CalendarDateField
                            label="Planned starting date"
                            value={data.planned_start_date ?? ''}
                            onChange={(iso) => setData('planned_start_date', iso)}
                            error={fieldErrors.planned_start_date}
                            className={fieldClass}
                        />
                        <TextInput
                            label="Area"
                            value={data.area ?? ''}
                            onChange={(event) => setData('area', event.target.value)}
                            error={fieldErrors.area}
                            className={fieldClass}
                        />
                        <TextInput
                            label="Block"
                            value={data.block ?? ''}
                            onChange={(event) => setData('block', event.target.value)}
                            error={fieldErrors.block}
                            className={fieldClass}
                        />
                        <TextInput
                            label="Road"
                            value={data.road ?? ''}
                            onChange={(event) => setData('road', event.target.value)}
                            error={fieldErrors.road}
                            className={fieldClass}
                        />
                        <TextInput
                            label="House number"
                            value={data.house_number ?? ''}
                            onChange={(event) => setData('house_number', event.target.value)}
                            error={fieldErrors.house_number}
                            className={fieldClass}
                        />
                        <TextInput
                            label="Gate / flat number"
                            value={data.gate_flat_number ?? ''}
                            onChange={(event) => setData('gate_flat_number', event.target.value)}
                            error={fieldErrors.gate_flat_number}
                            className={fieldClass}
                        />
                        <TextInput
                            label="Country"
                            value={data.country ?? ''}
                            onChange={(event) => setData('country', event.target.value)}
                            error={fieldErrors.country}
                            className={fieldClass}
                        />
                    </div>
                ) : (
                    <DefinitionList rows={deliveryRows} />
                )}
            </Section>

            <Section
                title="Confirm"
                sectionRef={confirmSectionRef}
                invalid={confirmInvalid}
                errorMessage={confirmInvalid ? 'Accept the declaration to continue.' : null}
            >
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
                        <span
                            className={[
                                'mt-0.5 inline-flex shrink-0 rounded-[4px] peer-focus-visible:ring-2 peer-focus-visible:ring-offset-2',
                                confirmInvalid
                                    ? 'peer-focus-visible:ring-status-error'
                                    : 'peer-focus-visible:ring-[#556C37]',
                            ].join(' ')}
                        >
                            <SquareCheckbox presentational checked={declarationChecked} />
                        </span>
                        <span
                            className={[
                                'font-montserrat text-sm font-bold uppercase leading-snug tracking-wide',
                                confirmInvalid ? 'text-status-error' : 'text-[#262A22]',
                            ].join(' ')}
                        >
                            I declare that all the information that was provided is true and to the best of my
                            knowledge
                        </span>
                    </label>
                    {fieldErrors.declaration_accepted ? (
                        <p className="text-sm text-status-error" role="alert">
                            {fieldErrors.declaration_accepted}
                        </p>
                    ) : null}

                    <ul className="space-y-2 font-body text-xs leading-relaxed text-[#555555] sm:text-sm">
                        <li>
                            Meal Craft only facilitates meal delivery. It is not responsible for Picniq kitchen
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
                    disabled={processing}
                    className="w-full min-w-[200px] max-w-sm uppercase tracking-[0.08em]"
                />
            </div>
        </form>
    );
}
