import InputError from '@/components/input-error';
import { NativeSelect } from '@/components/native-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarOff, PartyPopper, Trash2 } from 'lucide-react';
import { FormEvent } from 'react';

interface Props {
    time_off: { id: number; who: string; starts_at: string; ends_at: string; reason: string | null }[];
    holidays: { id: number; date: string; label: string; name: string }[];
    staff: { id: number; name: string }[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Ausencias y feriados', href: '/ausencias' }];

export default function TimeOffIndex({ time_off, holidays, staff }: Props) {
    const absence = useForm({ user_id: '', starts_at: '', ends_at: '', reason: '' });
    const holiday = useForm({ date: '', name: '' });

    const saveAbsence = (e: FormEvent) => {
        e.preventDefault();
        absence.post(route('time-off.store'), { preserveScroll: true, onSuccess: () => absence.reset() });
    };

    const saveHoliday = (e: FormEvent) => {
        e.preventDefault();
        holiday.post(route('holidays.store'), { preserveScroll: true, onSuccess: () => holiday.reset() });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ausencias y feriados" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Ausencias y feriados"
                    description="Bloqueá horarios puntuales o días completos: dejan de ofrecerse en la página de reservas al instante."
                />

                <div className="grid gap-6 xl:grid-cols-2">
                    <section className="bg-card rounded-xl border shadow-xs">
                        <header className="flex items-center gap-2 border-b px-5 py-4">
                            <CalendarOff className="size-4 text-violet-600" />
                            <h2 className="font-medium">Ausencias</h2>
                        </header>
                        <form onSubmit={saveAbsence} className="grid gap-3 border-b p-5 sm:grid-cols-2">
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="who">¿Quién?</Label>
                                <NativeSelect id="who" value={absence.data.user_id} onChange={(e) => absence.setData('user_id', e.target.value)}>
                                    <option value="">Todo el negocio</option>
                                    {staff.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="from">Desde</Label>
                                <Input
                                    id="from"
                                    type="datetime-local"
                                    step={300}
                                    value={absence.data.starts_at}
                                    onChange={(e) => absence.setData('starts_at', e.target.value)}
                                    required
                                />
                                <InputError message={absence.errors.starts_at} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="to">Hasta</Label>
                                <Input
                                    id="to"
                                    type="datetime-local"
                                    step={300}
                                    value={absence.data.ends_at}
                                    onChange={(e) => absence.setData('ends_at', e.target.value)}
                                    required
                                />
                                <InputError message={absence.errors.ends_at} />
                            </div>
                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="reason">Motivo (opcional)</Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="reason"
                                        placeholder="Vacaciones, trámite, capacitación…"
                                        value={absence.data.reason}
                                        onChange={(e) => absence.setData('reason', e.target.value)}
                                    />
                                    <Button disabled={absence.processing}>Agregar</Button>
                                </div>
                            </div>
                        </form>
                        <ul className="divide-y">
                            {time_off.length === 0 && (
                                <li className="text-muted-foreground px-5 py-6 text-center text-sm">Sin ausencias próximas.</li>
                            )}
                            {time_off.map((t) => (
                                <li key={t.id} className="flex items-center gap-3 px-5 py-3 text-sm">
                                    <div className="flex-1">
                                        <p className="font-medium">{t.who}</p>
                                        <p className="text-muted-foreground">
                                            {t.starts_at} → {t.ends_at}
                                            {t.reason && ` · ${t.reason}`}
                                        </p>
                                    </div>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        onClick={() => router.delete(route('time-off.destroy', t.id), { preserveScroll: true })}
                                        aria-label="Eliminar"
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section className="bg-card rounded-xl border shadow-xs">
                        <header className="flex items-center justify-between gap-2 border-b px-5 py-4">
                            <div className="flex items-center gap-2">
                                <PartyPopper className="size-4 text-violet-600" />
                                <h2 className="font-medium">Feriados y días cerrados</h2>
                            </div>
                            <code className="text-muted-foreground hidden rounded bg-zinc-100 px-2 py-0.5 text-xs md:block dark:bg-zinc-800">
                                php artisan holidays:import
                            </code>
                        </header>
                        <form onSubmit={saveHoliday} className="grid gap-3 border-b p-5 sm:grid-cols-[160px_1fr_auto] sm:items-end">
                            <div className="grid gap-2">
                                <Label htmlFor="date">Fecha</Label>
                                <Input
                                    id="date"
                                    type="date"
                                    value={holiday.data.date}
                                    onChange={(e) => holiday.setData('date', e.target.value)}
                                    required
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="hname">Nombre</Label>
                                <Input
                                    id="hname"
                                    placeholder="Nochebuena"
                                    value={holiday.data.name}
                                    onChange={(e) => holiday.setData('name', e.target.value)}
                                    required
                                />
                            </div>
                            <Button disabled={holiday.processing}>Agregar</Button>
                            <InputError message={holiday.errors.date} className="sm:col-span-3" />
                        </form>
                        <ul className="divide-y">
                            {holidays.length === 0 && (
                                <li className="text-muted-foreground px-5 py-6 text-center text-sm">No hay feriados cargados.</li>
                            )}
                            {holidays.map((h) => (
                                <li key={h.id} className="flex items-center gap-3 px-5 py-3 text-sm">
                                    <div className="flex-1">
                                        <p className="font-medium">{h.label}</p>
                                        <p className="text-muted-foreground">{h.name}</p>
                                    </div>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        onClick={() => router.delete(route('holidays.destroy', h.id), { preserveScroll: true })}
                                        aria-label="Eliminar"
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
