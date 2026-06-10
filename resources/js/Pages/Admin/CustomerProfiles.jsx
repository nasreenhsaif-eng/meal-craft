import adminInertiaLayout from '../../lib/adminInertiaLayout.jsx';

/**
 * @param {object} props
 * @param {Array<object>} props.customers
 */
function CustomerProfilesView({ customers }) {
    return (
        <div className="space-y-6">
            <div>
                <h1 className="font-montserrat text-2xl font-semibold text-[#262A22]">Customer Profiles</h1>
                <p className="mt-2 text-sm text-[#555555]">Registered customers and their onboarding status.</p>
            </div>

            <div className="overflow-hidden rounded-[12px] border border-gray-200 bg-white shadow-sm">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-[#F8F9F6]">
                        <tr>
                            <th className="px-4 py-3 text-left font-semibold text-[#555555]">Name</th>
                            <th className="px-4 py-3 text-left font-semibold text-[#555555]">Email</th>
                            <th className="px-4 py-3 text-left font-semibold text-[#555555]">Onboarding</th>
                            <th className="px-4 py-3 text-left font-semibold text-[#555555]">Calories</th>
                            <th className="px-4 py-3 text-left font-semibold text-[#555555]">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {customers.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-4 py-8 text-center text-[#6B7280]">
                                    No customer profiles yet.
                                </td>
                            </tr>
                        ) : (
                            customers.map((customer) => (
                                <tr key={customer.id}>
                                    <td className="px-4 py-3 font-medium text-[#262A22]">{customer.name}</td>
                                    <td className="px-4 py-3 text-[#555555]">{customer.email}</td>
                                    <td className="px-4 py-3 capitalize text-[#555555]">
                                        {customer.onboardingCompletedAt ? 'Complete' : customer.onboardingStep?.replace('_', ' ')}
                                    </td>
                                    <td className="px-4 py-3 text-[#555555]">{customer.dailyCalorieTarget ?? '—'}</td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`rounded-full px-2 py-1 text-xs font-semibold ${
                                                customer.isActive ? 'bg-[#E8EFE0] text-[#556C37]' : 'bg-red-50 text-red-700'
                                            }`}
                                        >
                                            {customer.isActive ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function CustomerProfiles(props) {
    return <CustomerProfilesView {...props} />;
}

CustomerProfiles.layout = adminInertiaLayout;
