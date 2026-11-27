import CustomerDetails from './CustomerDetails.jsx';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

/** Minimal intake field set matching {@link CustomerIntake::toFormArray}. */
const STORY_INTAKE_FORM = {
    first_name: 'Nasreen',
    last_name: 'Saif',
    email: 'nasreen@example.com',
    phone: '+973 3317 7718',
    contact_preference: 'whatsapp',
    date_of_birth: '1994-03-15',
    age: '32',
    gender: 'female',
    height_cm: '165',
    weight_kg: '68',
    target_weight_kg: '65',
    activity_level: 'lightly_active',
    daily_calorie_target: '1700',
    daily_calories_min: '1575',
    daily_calories_max: '1825',
    macro_split_style: 'balanced',
    delivery_time: 'morning',
    dislikes_and_allergies: 'Dairy',
    diet_protocol: 'balanced',
    plan_type: 'full',
    plan_days: '7',
    area: 'Adliya',
    block: '318',
    road: '1801',
    house_number: '12',
    gate_flat_number: '4',
    country: 'Bahrain',
    planned_start_date: '2026-11-15',
    follow_instagram: false,
    customer_question: '',
    declaration_accepted: false,
};

const STORY_INTAKE_OPTIONS = {
    contactPreferences: [
        { value: 'whatsapp', label: 'WhatsApp' },
        { value: 'email', label: 'Email' },
    ],
    deliveryTimes: [
        { value: 'morning', label: 'Morning' },
        { value: 'afternoon', label: 'Afternoon' },
    ],
    genders: [
        { value: 'female', label: 'Female' },
        { value: 'male', label: 'Male' },
    ],
    activityLevels: [
        { value: 'lightly_active', label: 'Lightly active' },
        { value: 'moderately_active', label: 'Moderately active' },
    ],
    planTypes: [{ value: 'full', label: 'Full Craft' }],
    planDays: [
        { value: '5', label: '5 days' },
        { value: '7', label: '7 days' },
    ],
    dietProtocols: [{ value: 'balanced', label: 'Balanced' }],
};

export default {
    title: 'Design System/06. Pages/Checkout/CustomerDetails',
    component: CustomerDetails,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component: 'Checkout details review — confirm intake and delivery fields before payment.',
            },
        },
    },
    decorators: withOnboardingMobileFrame,
};

export const Default = {
    name: 'Review details',
    render: () => (
        <CustomerDetails
            form={STORY_INTAKE_FORM}
            options={STORY_INTAKE_OPTIONS}
            uniqueCode="MC-DEMO-001"
            intakeSubmissionId="sub-demo-1"
            customerName="Nasreen"
            declarationAccepted={false}
        />
    ),
};