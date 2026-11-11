import { formatLongDate } from '@/lib/format';
import type { Appointment } from '@/types';
import { CalendarDays, Clock, Hash, Sparkles, UserRound, Wallet } from 'lucide-react';

export function AppointmentSummary({ appointment }: { appointment: Appointment }) {
    const rows = [
        { icon: Sparkles, label: 'Servicio', value: appointment.service?.name },
        { icon: UserRound, label: 'Profesional', value: appointment.staff?.name },
        { icon: CalendarDays, label: 'Fecha', value: formatLongDate(appointment.local_date) },
        { icon: Clock, label: 'Horario', value: `${appointment.local_start} a ${appointment.local_end} hs` },
        {
            icon: Wallet,
            label: 'Precio',
            value: appointment.deposit_cents > 0 ? `${appointment.price} (seña ${appointment.deposit})` : appointment.price,
        },
        { icon: Hash, label: 'Código', value: appointment.reference },
    ];

    return (
        <dl className="divide-y rounded-2xl border bg-white dark:bg-zinc-900">
            {rows.map(({ icon: Icon, label, value }) => (
                <div key={label} className="flex items-center gap-3 px-5 py-3 text-sm">
                    <Icon className="text-muted-foreground size-4" />
                    <dt className="text-muted-foreground w-28">{label}</dt>
                    <dd className="font-medium first-letter:uppercase">{value}</dd>
                </div>
            ))}
        </dl>
    );
}
