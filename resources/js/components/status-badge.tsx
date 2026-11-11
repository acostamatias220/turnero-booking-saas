import { STATUS_STYLES } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { AppointmentStatus } from '@/types';

export function StatusBadge({ status, label, className }: { status: AppointmentStatus; label: string; className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                STATUS_STYLES[status],
                className,
            )}
        >
            {label}
        </span>
    );
}
