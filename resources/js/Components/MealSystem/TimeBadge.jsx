import { IconClock } from '../Icons.jsx';

export default function TimeBadge({ minutes, className = '' }) {
    if (typeof minutes !== 'number') {
        return null;
    }

    return (
        <div className={`flex items-center gap-2 font-montserrat text-sm font-medium text-[#555555] ${className}`.trim()}>
            <IconClock className="text-[#556C37]" />
            <span>{minutes} min</span>
        </div>
    );
}

