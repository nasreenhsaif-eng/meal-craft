import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';

/**
 * @param {object} props
 */
export default function Home({ customerName, profile }) {
    return (
        <CustomerInertiaShell customerName={customerName}>
            <div className="rounded-[16px] border border-gray-200 bg-white p-8 shadow-sm">
                <h1 className="font-montserrat text-3xl font-semibold text-[#262A22]">Welcome back{customerName ? `, ${customerName}` : ''}</h1>
                <p className="mt-3 text-sm text-[#555555]">
                    Your personalized meal plan home. More customer features will appear here as the product grows.
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
            </div>
        </CustomerInertiaShell>
    );
}

Home.layout = (page) => page;
