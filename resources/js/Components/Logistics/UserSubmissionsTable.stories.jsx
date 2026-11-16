import { useState } from 'react';
import UserSubmissions from './UserSubmissions.jsx';
import { downloadCsv } from './logisticsCsvExport.js';
import { mockUserSubmissions } from './logisticsMockData.js';

function logisticsCsvDownload(filenameBase) {
    return (/** @type {string} */ csv) => {
        downloadCsv(`${filenameBase}.csv`, csv);
    };
}

export default {
    title: 'Design System/04. Organisms/Table',
    parameters: {
        layout: 'padded',
    },
};

export const UserSubmissionsTable = {
    name: 'User submissions',
    render: function Render() {
        const [date, setDate] = useState('');
        return (
            <div className="mx-auto max-w-6xl bg-[#F9FAFB] p-6">
                <UserSubmissions
                    submissions={mockUserSubmissions}
                    selectedDate={date}
                    onDateChange={setDate}
                    onPrint={() => window.print()}
                    onSendToGoogleDrive={logisticsCsvDownload('user-submissions-drive-all')}
                    onExportCsv={logisticsCsvDownload('user-submissions-export-all')}
                />
            </div>
        );
    },
};
