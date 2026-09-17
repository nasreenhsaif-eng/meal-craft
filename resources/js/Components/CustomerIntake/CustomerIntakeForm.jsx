import { useId } from 'react';
import Button from '../Atoms/Button/Button.jsx';
import SquareCheckbox from '../Atoms/Icons/SquareCheckbox.jsx';
import DropdownTextInput from '../Atoms/TextInput/DropdownTextInput.jsx';
import TextInput from '../Atoms/TextInput/TextInput.jsx';
import CalendarDateField from '../Molecules/Calendar/CalendarDateField.jsx';

/**
 * @typedef {{ value: string; label: string }} IntakeOption
 */

/**
 * @param {{
 *   label: string;
 *   value: string;
 *   options?: IntakeOption[];
 *   onChange: (value: string) => void;
 *   className?: string;
 * }} props
 */
function LabeledDropdown({ label, value, options = [], onChange, className = '' }) {
    const selected = options.find((option) => option.value === value);

    return (
        <DropdownTextInput
            label={label}
            value={selected?.label ?? ''}
            options={options.map((option) => option.label)}
            onChange={(nextLabel) => {
                const match = options.find((option) => option.label === nextLabel);
                onChange(match?.value ?? '');
            }}
            className={`!max-w-full ${className}`.trim()}
        />
    );
}

/**
 * @param {{
 *   label: string;
 *   value: string;
 *   onChange: (event: import('react').ChangeEvent<HTMLTextAreaElement>) => void;
 *   error?: string;
 *   rows?: number;
 * }} props
 */
function TextAreaField({ label, value, onChange, error, rows = 4 }) {
    const id = useId();
    const errorId = `${id}-error`;

    return (
        <div className="block w-full text-left">
            <label
                htmlFor={id}
                className="mb-2 block font-montserrat text-sm font-bold leading-snug tracking-tight text-grey-94"
            >
                {label}
            </label>
            <textarea
                id={id}
                rows={rows}
                value={value}
                onChange={onChange}
                aria-invalid={error ? 'true' : 'false'}
                aria-describedby={error ? errorId : undefined}
                className={[
                    'w-full rounded-[12px] border bg-white px-5 py-3 font-body text-[16px] tracking-tight text-[#364153]',
                    'shadow-sm outline-none transition-[border-color,box-shadow] duration-200',
                    'placeholder:text-[#364153]/50',
                    error
                        ? 'border-status-error focus:border-status-error'
                        : 'border-[#E5E7EB] focus:border-[#6E8C47]',
                ].join(' ')}
            />
            {error ? (
                <p id={errorId} className="mt-1.5 text-sm text-status-error" role="alert">
                    {error}
                </p>
            ) : null}
        </div>
    );
}

/**
 * @param {{
 *   label: string;
 *   checked: boolean;
 *   onChange: (checked: boolean) => void;
 * }} props
 */
function CheckboxField({ label, checked, onChange }) {
    const id = useId();

    return (
        <label htmlFor={id} className="group/item flex cursor-pointer select-none items-start gap-3">
            <input
                id={id}
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                className="peer sr-only"
            />
            <span className="mt-0.5 inline-flex shrink-0 rounded-[4px] peer-focus-visible:ring-2 peer-focus-visible:ring-[#556C37] peer-focus-visible:ring-offset-2">
                <SquareCheckbox presentational checked={checked} />
            </span>
            <span className="font-montserrat text-sm font-medium leading-snug text-[#364153]">{label}</span>
        </label>
    );
}

/**
 * @param {{ title: string; children: import('react').ReactNode }} props
 */
function Section({ title, children }) {
    return (
        <section className="rounded-[12px] border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 className="m-0 font-montserrat text-base font-bold tracking-tight text-[#262A22]">{title}</h2>
            <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 [&>*]:min-w-0">{children}</div>
        </section>
    );
}

/**
 * Shared customer intake fields for checkout and the admin profile editor.
 *
 * @param {{
 *   data: Record<string, mixed>;
 *   setData: (key: string, value: mixed) => void;
 *   errors?: Record<string, string>;
 *   processing?: boolean;
 *   options?: Record<string, IntakeOption[]>;
 *   uniqueCode?: string;
 *   intakeSubmissionId?: string;
 *   submitLabel?: string;
 *   onSubmit: () => void;
 * }} props
 */
export default function CustomerIntakeForm({
    data,
    setData,
    errors = {},
    processing = false,
    options = {},
    uniqueCode = '',
    intakeSubmissionId = '',
    submitLabel = 'Save details',
    onSubmit,
}) {
    const fieldClass = 'w-full !max-w-full';

    return (
        <form
            className="flex flex-col gap-5"
            onSubmit={(event) => {
                event.preventDefault();
                onSubmit();
            }}
        >
            <Section title="Contact">
                <TextInput
                    label="First name"
                    value={data.first_name ?? ''}
                    onChange={(event) => setData('first_name', event.target.value)}
                    error={errors.first_name}
                    autoComplete="given-name"
                    className={fieldClass}
                />
                <TextInput
                    label="Last name"
                    value={data.last_name ?? ''}
                    onChange={(event) => setData('last_name', event.target.value)}
                    error={errors.last_name}
                    autoComplete="family-name"
                    className={fieldClass}
                />
                <TextInput
                    label="Email"
                    type="email"
                    value={data.email ?? ''}
                    onChange={(event) => setData('email', event.target.value)}
                    error={errors.email}
                    autoComplete="email"
                    className={fieldClass}
                />
                <TextInput
                    label="Phone number"
                    type="tel"
                    value={data.phone ?? ''}
                    onChange={(event) => setData('phone', event.target.value)}
                    error={errors.phone}
                    autoComplete="tel"
                    className={fieldClass}
                />
                <LabeledDropdown
                    label="How would you like us to contact you"
                    value={data.contact_preference ?? ''}
                    options={options.contactPreferences}
                    onChange={(value) => setData('contact_preference', value)}
                />
            </Section>

            <Section title="Body & plan">
                <TextInput
                    label="Date of birth"
                    type="date"
                    value={data.date_of_birth ?? ''}
                    onChange={(event) => setData('date_of_birth', event.target.value)}
                    error={errors.date_of_birth}
                    className={fieldClass}
                />
                <LabeledDropdown
                    label="Gender"
                    value={data.gender ?? ''}
                    options={options.genders}
                    onChange={(value) => setData('gender', value)}
                />
                <TextInput
                    label="Height (cm)"
                    type="number"
                    value={data.height_cm ?? ''}
                    onChange={(event) => setData('height_cm', event.target.value)}
                    error={errors.height_cm}
                    className={fieldClass}
                />
                <TextInput
                    label="Weight (kg)"
                    type="number"
                    value={data.weight_kg ?? ''}
                    onChange={(event) => setData('weight_kg', event.target.value)}
                    error={errors.weight_kg}
                    className={fieldClass}
                />
                <TextInput
                    label="Target weight (kg)"
                    type="number"
                    value={data.target_weight_kg ?? ''}
                    onChange={(event) => setData('target_weight_kg', event.target.value)}
                    error={errors.target_weight_kg}
                    className={fieldClass}
                />
                <LabeledDropdown
                    label="Activity level"
                    value={data.activity_level ?? ''}
                    options={options.activityLevels}
                    onChange={(value) => setData('activity_level', value)}
                />
                <LabeledDropdown
                    label="Type of plan"
                    value={data.plan_type ?? ''}
                    options={options.planTypes}
                    onChange={(value) => setData('plan_type', value)}
                />
                <LabeledDropdown
                    label="Days"
                    value={data.plan_days ?? ''}
                    options={options.planDays}
                    onChange={(value) => setData('plan_days', value)}
                />
                <LabeledDropdown
                    label="Diet protocol"
                    value={data.diet_protocol ?? ''}
                    options={options.dietProtocols}
                    onChange={(value) => setData('diet_protocol', value)}
                />
                <div className="md:col-span-2">
                    <TextAreaField
                        label="Dislikes & allergies"
                        value={data.dislikes_and_allergies ?? ''}
                        onChange={(event) => setData('dislikes_and_allergies', event.target.value)}
                        error={errors.dislikes_and_allergies}
                    />
                </div>
            </Section>

            <Section title="Delivery address">
                <LabeledDropdown
                    label="Delivery time"
                    value={data.delivery_time ?? ''}
                    options={options.deliveryTimes}
                    onChange={(value) => setData('delivery_time', value)}
                />
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
            </Section>

            <Section title="Other">
                <div className="md:col-span-2">
                    <CheckboxField
                        label="Follow us on Instagram for updates"
                        checked={Boolean(data.follow_instagram)}
                        onChange={(checked) => setData('follow_instagram', checked)}
                    />
                </div>
                <div className="md:col-span-2">
                    <CheckboxField
                        label="Some of our clients enjoy the food too much and requested not to be on a plan. (This is an uncalculated plan — just enjoy the food.)"
                        checked={Boolean(data.uncalculated_plan)}
                        onChange={(checked) => setData('uncalculated_plan', checked)}
                    />
                </div>
                <div className="md:col-span-2">
                    <TextAreaField
                        label="Type a question"
                        value={data.customer_question ?? ''}
                        onChange={(event) => setData('customer_question', event.target.value)}
                        error={errors.customer_question}
                    />
                </div>
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
                    value={intakeSubmissionId || 'Assigned on first save'}
                    onChange={() => {}}
                    readOnly
                    disabled
                    className={fieldClass}
                />
            </Section>

            <div>
                <Button type="submit" label={processing ? 'Saving…' : submitLabel} disabled={processing} />
            </div>
        </form>
    );
}
