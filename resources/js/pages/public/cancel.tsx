import { AppointmentSummary } from '@/components/appointment-summary';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';
import type { Appointment, PublicBusiness } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { FormEvent } from 'react';

interface Props {
    business: PublicBusiness;
    appointment: Appointment;
    can_cancel: boolean;
    action: string;
}

export default function CancelAppointment({ business, appointment, can_cancel, action }: Props) {
    const { post, processing } = useForm({});

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(action);
    };

    return (
        <PublicLayout business={business}>
            <Head title="Cancelar turno" />
            <div className="mx-auto max-w-xl space-y-6">
                <div>
                    <h2 className="text-xl font-semibold">¿Querés cancelar este turno?</h2>
                    <p className="text-muted-foreground text-sm">Al cancelar, el horario queda libre para otra persona.</p>
                </div>
                <AppointmentSummary appointment={appointment} />
                {can_cancel ? (
                    <form onSubmit={submit} className="flex flex-wrap gap-3">
                        <Button type="submit" variant="destructive" disabled={processing}>
                            Sí, cancelar turno
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href={route('public.appointments.show', [business.slug, appointment.id])}>No, mantenerlo</Link>
                        </Button>
                    </form>
                ) : (
                    <div className="flex gap-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                        <TriangleAlert className="size-5 shrink-0" />
                        <p>
                            Este turno ya no se puede cancelar online (se permite hasta {business.cancellation_notice_hours} h antes, y solo turnos
                            activos).
                            {business.phone && ` Comunicate al ${business.phone}.`}
                        </p>
                    </div>
                )}
            </div>
        </PublicLayout>
    );
}
