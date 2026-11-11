import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { addDays, AGENDA_STYLES } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type Appointment, type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { CalendarX2, ChevronLeft, ChevronRight, Clock, Mail, Phone, User } from 'lucide-react';
import { useState } from 'react';

type AgendaAppointment = Appointment & { start_minute: number; end_minute: number };

interface Props {
    date: string;
    date_label: string;
    is_today: boolean;
    holiday: string | null;
    range: { start: number; end: number };
    staff: {
        id: number;
        name: string;
        title: string | null;
        working: { start: string; end: string }[];
        time_off: { start_minute: number; end_minute: number; reason: string | null }[];
    }[];
    appointments: AgendaAppointment[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Agenda', href: '/agenda' }];
const HOUR_PX = 64;

const toMinutes = (hhmm: string) => {
    const [h, m] = hhmm.split(':').map(Number);
    return h * 60 + m;
};

export default function Agenda({ date, date_label, is_today, holiday, range, staff, appointments }: Props) {
    const [selected, setSelected] = useState<AgendaAppointment | null>(null);
    const startMin = range.start * 60;
    const totalMin = (range.end - range.start) * 60;
    const top = (minute: number) => ((minute - startMin) / 60) * HOUR_PX;
    const hours = Array.from({ length: range.end - range.start + 1 }, (_, i) => range.start + i);

    const go = (d: string) => router.get('/agenda', { date: d }, { preserveScroll: true, preserveState: true });

    const changeStatus = (status: 'completed' | 'no_show' | 'cancelled') => {
        if (!selected) return;
        if (status === 'cancelled' && !window.confirm('¿Cancelar este turno? Le avisaremos al cliente por e-mail.')) return;
        router.patch(route('appointments.status', selected.id), { status }, { preserveScroll: true, onSuccess: () => setSelected(null) });
    };

    const isPast = selected ? new Date(selected.starts_at) <= new Date() : false;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Agenda" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{date_label}</h1>
                        <p className="text-muted-foreground text-sm">
                            {appointments.filter((a) => a.status !== 'cancelled').length} turnos · {staff.length}{' '}
                            {staff.length === 1 ? 'profesional' : 'profesionales'}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="icon" onClick={() => go(addDays(date, -1))} aria-label="Día anterior">
                            <ChevronLeft className="size-4" />
                        </Button>
                        <Button variant={is_today ? 'secondary' : 'outline'} onClick={() => router.get('/agenda')}>
                            Hoy
                        </Button>
                        <Button variant="outline" size="icon" onClick={() => go(addDays(date, 1))} aria-label="Día siguiente">
                            <ChevronRight className="size-4" />
                        </Button>
                        <Input type="date" value={date} onChange={(e) => e.target.value && go(e.target.value)} className="w-40" />
                    </div>
                </div>

                {holiday && (
                    <div className="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                        <CalendarX2 className="size-4" /> Cerrado por feriado: <strong>{holiday}</strong>. No se ofrecen turnos este día.
                    </div>
                )}

                <div className="bg-card overflow-x-auto rounded-xl border shadow-xs">
                    <div className="grid min-w-[640px]" style={{ gridTemplateColumns: `64px repeat(${staff.length}, minmax(180px, 1fr))` }}>
                        <div className="bg-card sticky top-0 z-10 border-b" />
                        {staff.map((s) => (
                            <div key={s.id} className="bg-card sticky top-0 z-10 flex items-center gap-2 border-b border-l px-3 py-3">
                                <span className="flex size-8 items-center justify-center rounded-full bg-violet-100 text-xs font-semibold text-violet-700 dark:bg-violet-500/20 dark:text-violet-200">
                                    {s.name
                                        .split(' ')
                                        .slice(0, 2)
                                        .map((p) => p[0])
                                        .join('')}
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">{s.name}</p>
                                    <p className="text-muted-foreground truncate text-xs">
                                        {s.working.length ? s.working.map((w) => `${w.start}–${w.end}`).join(' · ') : 'No trabaja'}
                                    </p>
                                </div>
                            </div>
                        ))}

                        <div className="relative" style={{ height: (totalMin / 60) * HOUR_PX }}>
                            {hours.map((h) => (
                                <span
                                    key={h}
                                    className="text-muted-foreground absolute right-2 -translate-y-1/2 text-[11px] tabular-nums"
                                    style={{ top: top(h * 60) }}
                                >
                                    {String(h).padStart(2, '0')}:00
                                </span>
                            ))}
                        </div>

                        {staff.map((s) => (
                            <div
                                key={s.id}
                                className="relative border-l bg-zinc-50/80 dark:bg-zinc-900/40"
                                style={{ height: (totalMin / 60) * HOUR_PX }}
                            >
                                {s.working.map((w) => (
                                    <div
                                        key={w.start}
                                        className="bg-card absolute inset-x-0"
                                        style={{ top: top(toMinutes(w.start)), height: top(toMinutes(w.end)) - top(toMinutes(w.start)) }}
                                    />
                                ))}
                                {hours.map((h) => (
                                    <div
                                        key={h}
                                        className="absolute inset-x-0 border-t border-dashed border-zinc-200 dark:border-zinc-800"
                                        style={{ top: top(h * 60) }}
                                    />
                                ))}
                                {s.time_off.map((t, i) => (
                                    <div
                                        key={i}
                                        className="absolute inset-x-1 flex items-start rounded-md bg-[repeating-linear-gradient(45deg,transparent,transparent_6px,rgba(113,113,122,0.12)_6px,rgba(113,113,122,0.12)_12px)] p-2 text-xs text-zinc-500"
                                        style={{
                                            top: top(Math.max(t.start_minute, startMin)),
                                            height: top(Math.min(t.end_minute, range.end * 60)) - top(Math.max(t.start_minute, startMin)),
                                        }}
                                    >
                                        {t.reason ?? 'Ausencia'}
                                    </div>
                                ))}
                                {appointments
                                    .filter((a) => a.staff?.id === s.id)
                                    .map((a) => (
                                        <button
                                            key={a.id}
                                            type="button"
                                            onClick={() => setSelected(a)}
                                            className={cn(
                                                'absolute inset-x-1 overflow-hidden rounded-md border-l-4 px-2 py-1 text-left text-xs shadow-xs transition hover:z-10 hover:shadow-md focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:outline-none',
                                                AGENDA_STYLES[a.status],
                                            )}
                                            style={{
                                                top: top(a.start_minute) + 1,
                                                height: Math.max(22, top(a.end_minute) - top(a.start_minute) - 2),
                                            }}
                                        >
                                            <p className="truncate font-semibold">
                                                {a.local_start} · {a.customer?.name}
                                            </p>
                                            <p className="truncate opacity-80">{a.service?.name}</p>
                                        </button>
                                    ))}
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <Sheet open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}>
                <SheetContent className="w-full sm:max-w-md">
                    {selected && (
                        <>
                            <SheetHeader>
                                <SheetTitle className="flex items-center gap-2">
                                    {selected.service?.name} <StatusBadge status={selected.status} label={selected.status_label} />
                                </SheetTitle>
                                <SheetDescription>Código {selected.reference}</SheetDescription>
                            </SheetHeader>
                            <div className="space-y-4 px-4 text-sm">
                                <p className="flex items-center gap-2">
                                    <Clock className="text-muted-foreground size-4" /> {selected.local_start}–{selected.local_end} con{' '}
                                    {selected.staff?.name}
                                </p>
                                <p className="flex items-center gap-2">
                                    <User className="text-muted-foreground size-4" /> {selected.customer?.name}
                                </p>
                                <p className="flex items-center gap-2">
                                    <Mail className="text-muted-foreground size-4" />{' '}
                                    <a className="underline" href={`mailto:${selected.customer?.email}`}>
                                        {selected.customer?.email}
                                    </a>
                                </p>
                                {selected.customer?.phone && (
                                    <p className="flex items-center gap-2">
                                        <Phone className="text-muted-foreground size-4" /> {selected.customer.phone}
                                    </p>
                                )}
                                <div className="rounded-lg border p-3">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Precio</span> <span className="font-medium">{selected.price}</span>
                                    </div>
                                    {selected.deposit_cents > 0 && (
                                        <div className="mt-1 flex justify-between">
                                            <span className="text-muted-foreground">Seña</span>
                                            <span>
                                                {selected.deposit} · {selected.payment?.status_label ?? 'sin pago'}
                                            </span>
                                        </div>
                                    )}
                                </div>
                                {selected.notes && <p className="bg-muted rounded-lg p-3 italic">“{selected.notes}”</p>}

                                <div className="grid grid-cols-2 gap-2 pt-2">
                                    {selected.status === 'confirmed' && isPast && (
                                        <>
                                            <Button onClick={() => changeStatus('completed')}>Atendido</Button>
                                            <Button variant="outline" onClick={() => changeStatus('no_show')}>
                                                No vino
                                            </Button>
                                        </>
                                    )}
                                    {['confirmed', 'pending_payment'].includes(selected.status) && (
                                        <Button variant="destructive" className="col-span-2" onClick={() => changeStatus('cancelled')}>
                                            Cancelar turno
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
