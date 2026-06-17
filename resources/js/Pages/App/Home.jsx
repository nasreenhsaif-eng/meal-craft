import Button from '../../Components/Atoms/Button/Button.jsx';
import CustomerAppHeaderActions from '../../Components/Molecules/Customer/CustomerAppHeaderActions.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';

/**
 * @param {object} props
 * @param {string} [props.consultationUrl]
 * @param {{ craftKey?: string; weekDuration?: number; submittedAt?: string } | null} [props.craftPlan]
 */
export default function Home({ customerName, profile, consultationUrl = '/consultation/crafted-for-you', craftPlan = null }) {
    const hasSubmittedPlan = Boolean(craftPlan?.submittedAt);

    return (
        <CustomerInertiaShell
            customerName={customerName}
            headerActions={<CustomerAppHeaderActions />}
        >
            <div className="rounded-[16px] border border-gray-200 bg-white p-8 shadow-sm">
                <h1 className="font-montserrat text-3xl font-semibold text-[#262A22]">Welcome back{customerName ? `, ${customerName}` : ''}</h1>
                <p className="mt-3 text-sm text-[#555555]">
                    {hasSubmittedPlan
                        ? 'Your meal selections are saved. You can update them anytime before the kitchen starts production.'
                        : 'Your targets are set. Next, choose the meals you want for your week.'}
                </p>

                {profile ? (
                    <dl className="mt-8 grid gap-4 rounded-[12px] bg-[#F8F9F6] p-5 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-[#555555]">Daily calories</dt>
                            <dd className="mt-1 text-lg font-semibold text-[#262A22]">{profile.dailyCalorieTarget}</dd>
                        </div>
                        <div>
                            <dt className="text-[#555555]">Macro style</dt>
                            <dd className="mt-1 text-lg font-semibold capitalize text-[#262A22]">
                                {profile.macroSplitStyle?.replace('_', ' ')}
                            </dd>
                        </div>
                    </dl>
                ) : null}

                <div className="mt-8 rounded-[12px] border border-[#6E8C47]/20 bg-[#6E8C47]/5 p-6">
                    <h2 className="font-montserrat text-xl font-semibold text-[#262A22]">
                        {hasSubmittedPlan ? 'Update your meals' : 'Choose your meals'}
                    </h2>
                    <p className="mt-2 text-sm text-[#555555]">
                        Browse breakfasts, mains, salads, desserts, and optional soup — portioned to your {profile?.dailyCalorieTarget ?? 'plan'} kcal target.
                    </p>
                    <div className="mt-5">
                        <Button
                            type="button"
                            label={hasSubmittedPlan ? 'Edit meal plan' : 'Start meal selection'}
                            onClick={() => window.location.assign(consultationUrl)}
                            className="min-w-[200px]"
                        />
                    </div>
                </div>
            </div>
        </CustomerInertiaShell>
    );
}

Home.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
