import { AppointmentSummary } from '@/components/appointment-summary';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';
import { cn } from '@/lib/utils';
import type { Appointment, PublicBusiness } from '@/types';
import { Head, Link, usePoll } from '@inertiajs/react';
import { CalendarPlus, CircleCheck, CircleX, Hourglass, LoaderCircle, RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
    business: PublicBusiness;
    appointment: Appointment;
}

const HERO: Record<string, { icon: typeof CircleCheck; title: string; text: string; tone: string }> = {
    confirmed: {
        icon: CircleCheck,
        title: '¡Turno confirmado!',
        text: 'Te enviamos la confirmación por e-mail, con el link para cancelar si lo necesitás.',
        tone: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
    },
    pending_payment: {
        icon: Hourglass,
        title: 'Esperando el pago de la seña',
        text: 'Apenas el proveedor nos confirme el pago, tu turno queda confirmado. Esta página se actualiza sola.',
        tone: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
    },
    completed: {
        icon: CircleCheck,
        title: 'Turno realizado',
        text: '¡Gracias por elegirnos!',
        tone: 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
    },
    cancelled: {
        icon: CircleX,
        title: 'Turno cancelado',
        text: 'El horario quedó libre para otra persona.',
        tone: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    },
    expired: {
        icon: CircleX,
        title: 'La reserva venció',
        text: 'No recibimos el pago a tiempo y liberamos el horario. Podés volver a reservar.',
        tone: 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
    },
    no_show: {
        icon: CircleX,
        title: 'Ausente',
        text: 'Este turno se marcó como ausente.',
        tone: 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
    },
};

function useCountdown(until: string | null) {
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        if (!until) return;
        const id = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(id);
    }, [until]);
    if (!until) return null;
    const left = Math.max(0, Math.floor((new Date(until).getTime() - now) / 1000));
    return `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
}

export default function AppointmentStatusPage({ business, appointment }: Props) {
    const pending = appointment.status === 'pending_payment';
    const { stop, start } = usePoll(2000, { only: ['appointment'] }, { autoStart: false });
    const countdown = useCountdown(pending ? appointment.hold_expires_at : null);
    const hero = HERO[appointment.status];
    const Icon = hero.icon;

    useEffect(() => {
        if (pending) start();
        else stop();
        return stop;
    }, [pending, start, stop]);

    return (
        <PublicLayout business={business}>
            <Head title={`Turno ${appointment.reference}`} />
            <div className="mx-auto max-w-xl space-y-6">
                <div className={cn('flex gap-4 rounded-2xl p-5', hero.tone)}>
                    {pending ? <LoaderCircle className="size-7 shrink-0 animate-spin" /> : <Icon className="size-7 shrink-0" />}
                    <div>
                        <h2 className="text-lg font-semibold">{hero.title}</h2>
                        <p className="text-sm opacity-90">{hero.text}</p>
                        {pending && countdown && (
                            <p className="mt-2 text-sm">
                                Te guardamos el horario por <span className="font-semibold tabular-nums">{countdown}</span> min.
                            </p>
                        )}
                    </div>
                </div>

                <AppointmentSummary appointment={appointment} />

                {appointment.payment && (
                    <p className="text-muted-foreground text-center text-sm">
                        Seña {appointment.payment.amount} · <span className="font-medium">{appointment.payment.status_label}</span>
                    </p>
                )}

                <div className="flex flex-wrap justify-center gap-3">
                    {appointment.status === 'confirmed' && (
                        <Button asChild variant="outline">
                            <a href={route('public.appointments.calendar', [business.slug, appointment.id])}>
                                <CalendarPlus className="size-4" /> Agregar al calendario
                            </a>
                        </Button>
                    )}
                    {['cancelled', 'expired'].includes(appointment.status) && (
                        <Button asChild className="bg-[var(--brand)] text-white hover:bg-[var(--brand)]/90">
                            <Link href={route('public.booking', business.slug)}>
                                <RotateCcw className="size-4" /> Reservar otro turno
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
        </PublicLayout>
    );
}
