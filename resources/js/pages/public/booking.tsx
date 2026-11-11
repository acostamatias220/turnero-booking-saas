import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PublicLayout from '@/layouts/public-layout';
import { formatMinutes } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PublicBusiness, Service, StaffMember } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, Check, Clock, LoaderCircle, ShieldCheck, Sparkles, UserRound } from 'lucide-react';
import { FormEvent, useEffect, useMemo, useState } from 'react';

interface Props {
    business: PublicBusiness;
    services: Service[];
    staff: StaffMember[];
}

interface Slot {
    starts_at: string;
    time: string;
    staff_ids: number[];
}

const steps = ['Servicio', 'Profesional', 'Día y horario', 'Tus datos'];

function dayParts(isoDate: string) {
    const [y, m, d] = isoDate.split('-').map(Number);
    const date = new Date(Date.UTC(y, m - 1, d));
    const fmt = (o: Intl.DateTimeFormatOptions) => new Intl.DateTimeFormat('es-AR', { ...o, timeZone: 'UTC' }).format(date);
    return {
        weekday: fmt({ weekday: 'short' }).replace('.', ''),
        day: d,
        month: fmt({ month: 'short' }).replace('.', ''),
        long: fmt({ weekday: 'long', day: 'numeric', month: 'long' }),
    };
}

export default function Booking({ business, services, staff }: Props) {
    const [step, setStep] = useState(0);
    const [service, setService] = useState<Service | null>(null);
    const [staffId, setStaffId] = useState<number | null>(null);
    const [dates, setDates] = useState<string[] | null>(null);
    const [date, setDate] = useState<string | null>(null);
    const [slots, setSlots] = useState<Slot[] | null>(null);
    const [slot, setSlot] = useState<Slot | null>(null);

    const form = useForm({ name: '', email: '', phone: '', notes: '', website: '' });
    const api = `/api/v1/public/businesses/${business.slug}`;
    const query = useMemo(() => {
        const params = new URLSearchParams();
        if (service) params.set('service_id', String(service.id));
        if (staffId) params.set('staff_id', String(staffId));
        return params;
    }, [service, staffId]);

    const available = service ? staff.filter((s) => service.staff_ids?.includes(s.id)) : [];

    useEffect(() => {
        if (step !== 2 || !service) return;
        setDates(null);
        fetch(`${api}/available-dates?${query}`, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((json) => {
                setDates(json.data.dates);
                setDate((current) => (current && json.data.dates.includes(current) ? current : (json.data.dates[0] ?? null)));
            });
    }, [step, api, query, service]);

    useEffect(() => {
        if (!date || !service) return;
        setSlots(null);
        setSlot(null);
        fetch(`${api}/availability?${query}&date=${date}`, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((json) => setSlots(json.data.slots));
    }, [date, api, query, service]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (!service || !slot) return;
        form.transform((data) => ({ ...data, service_id: service.id, staff_id: staffId, starts_at: slot.starts_at }));
        form.post(route('public.appointments.store', business.slug), {
            onError: () => setStep(3),
        });
    };

    const deposit = service && business.deposit_percent > 0 ? Math.round((service.price_cents * business.deposit_percent) / 100) : 0;
    const morning = slots?.filter((s) => Number(s.time.slice(0, 2)) < 13) ?? [];
    const afternoon = slots?.filter((s) => Number(s.time.slice(0, 2)) >= 13) ?? [];
    const staffName = (id: number | null) => staff.find((s) => s.id === id)?.name ?? 'Cualquier profesional';

    return (
        <PublicLayout business={business}>
            <Head title={`Reservá en ${business.name}`} />

            <ol className="mb-8 grid grid-cols-4 gap-2" aria-label="Pasos">
                {steps.map((label, i) => (
                    <li key={label} className="space-y-2">
                        <div
                            className={cn('h-1.5 rounded-full transition-colors', i <= step ? 'bg-[var(--brand)]' : 'bg-zinc-200 dark:bg-zinc-800')}
                        />
                        <p className={cn('hidden text-xs sm:block', i === step ? 'font-semibold' : 'text-muted-foreground')}>
                            {i + 1}. {label}
                        </p>
                    </li>
                ))}
            </ol>

            <div className="grid gap-8 lg:grid-cols-[1fr_300px]">
                <section>
                    {step > 0 && (
                        <button
                            type="button"
                            onClick={() => setStep(step - 1)}
                            className="text-muted-foreground hover:text-foreground mb-4 flex items-center gap-1 text-sm"
                        >
                            <ArrowLeft className="size-4" /> Volver
                        </button>
                    )}

                    {step === 0 && (
                        <div className="space-y-3">
                            <h2 className="text-xl font-semibold">¿Qué te querés hacer?</h2>
                            {services.map((s) => (
                                <button
                                    key={s.id}
                                    type="button"
                                    onClick={() => {
                                        setService(s);
                                        setStaffId(null);
                                        setStep(1);
                                    }}
                                    className="group flex w-full items-center gap-4 rounded-2xl border bg-white p-4 text-left shadow-xs transition hover:-translate-y-0.5 hover:border-[var(--brand)] hover:shadow-md dark:bg-zinc-900"
                                >
                                    <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[var(--brand)]/10 text-[var(--brand)]">
                                        <Sparkles className="size-5" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="font-semibold">{s.name}</p>
                                        {s.description && <p className="text-muted-foreground line-clamp-1 text-sm">{s.description}</p>}
                                        <p className="text-muted-foreground mt-1 flex items-center gap-1 text-xs">
                                            <Clock className="size-3.5" /> {formatMinutes(s.duration_minutes)}
                                        </p>
                                    </div>
                                    <p className="font-semibold tabular-nums">{s.price}</p>
                                </button>
                            ))}
                        </div>
                    )}

                    {step === 1 && service && (
                        <div className="space-y-3">
                            <h2 className="text-xl font-semibold">¿Con quién?</h2>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {[
                                    {
                                        id: null as number | null,
                                        name: 'Cualquier profesional',
                                        title: 'Te asignamos quien esté libre',
                                        initials: '★',
                                    },
                                    ...available,
                                ].map((s) => (
                                    <button
                                        key={s.id ?? 'any'}
                                        type="button"
                                        onClick={() => {
                                            setStaffId(s.id);
                                            setStep(2);
                                        }}
                                        className="flex items-center gap-3 rounded-2xl border bg-white p-4 text-left shadow-xs transition hover:-translate-y-0.5 hover:border-[var(--brand)] hover:shadow-md dark:bg-zinc-900"
                                    >
                                        <span className="flex size-11 items-center justify-center rounded-full bg-[var(--brand)] font-semibold text-white">
                                            {s.initials}
                                        </span>
                                        <div>
                                            <p className="font-semibold">{s.name}</p>
                                            <p className="text-muted-foreground text-sm">{s.title ?? 'Profesional'}</p>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="space-y-5">
                            <h2 className="text-xl font-semibold">Elegí día y horario</h2>
                            {dates === null ? (
                                <p className="text-muted-foreground flex items-center gap-2 text-sm">
                                    <LoaderCircle className="size-4 animate-spin" /> Buscando disponibilidad…
                                </p>
                            ) : dates.length === 0 ? (
                                <p className="text-muted-foreground rounded-xl border bg-white p-6 text-center text-sm dark:bg-zinc-900">
                                    No hay turnos disponibles en los próximos días. Probá con otro profesional.
                                </p>
                            ) : (
                                <>
                                    <div className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-2">
                                        {dates.map((d) => {
                                            const p = dayParts(d);
                                            return (
                                                <button
                                                    key={d}
                                                    type="button"
                                                    onClick={() => setDate(d)}
                                                    className={cn(
                                                        'flex w-16 shrink-0 flex-col items-center rounded-xl border bg-white py-2 transition dark:bg-zinc-900',
                                                        d === date
                                                            ? 'border-[var(--brand)] bg-[var(--brand)] text-white dark:bg-[var(--brand)]'
                                                            : 'hover:border-[var(--brand)]',
                                                    )}
                                                >
                                                    <span className="text-xs capitalize opacity-80">{p.weekday}</span>
                                                    <span className="text-lg font-semibold">{p.day}</span>
                                                    <span className="text-xs capitalize opacity-80">{p.month}</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                    {slots === null ? (
                                        <p className="text-muted-foreground flex items-center gap-2 text-sm">
                                            <LoaderCircle className="size-4 animate-spin" /> Cargando horarios…
                                        </p>
                                    ) : (
                                        [
                                            ['Mañana', morning],
                                            ['Tarde', afternoon],
                                        ].map(([label, list]) =>
                                            (list as Slot[]).length === 0 ? null : (
                                                <div key={label as string}>
                                                    <p className="text-muted-foreground mb-2 text-sm font-medium">{label as string}</p>
                                                    <div className="grid grid-cols-3 gap-2 sm:grid-cols-5">
                                                        {(list as Slot[]).map((s) => (
                                                            <button
                                                                key={s.starts_at}
                                                                type="button"
                                                                onClick={() => {
                                                                    setSlot(s);
                                                                    setStep(3);
                                                                }}
                                                                className={cn(
                                                                    'rounded-lg border bg-white py-2 text-sm font-medium tabular-nums transition hover:border-[var(--brand)] hover:text-[var(--brand)] dark:bg-zinc-900',
                                                                    slot?.starts_at === s.starts_at && 'border-[var(--brand)] text-[var(--brand)]',
                                                                )}
                                                            >
                                                                {s.time}
                                                            </button>
                                                        ))}
                                                    </div>
                                                </div>
                                            ),
                                        )
                                    )}
                                </>
                            )}
                        </div>
                    )}

                    {step === 3 && slot && (
                        <form onSubmit={submit} className="space-y-4">
                            <h2 className="text-xl font-semibold">Tus datos</h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nombre y apellido</Label>
                                    <Input
                                        id="name"
                                        autoComplete="name"
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        required
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">E-mail</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        autoComplete="email"
                                        value={form.data.email}
                                        onChange={(e) => form.setData('email', e.target.value)}
                                        required
                                    />
                                    <InputError message={form.errors.email} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Celular (opcional)</Label>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        autoComplete="tel"
                                        placeholder="11 5555-1234"
                                        value={form.data.phone}
                                        onChange={(e) => form.setData('phone', e.target.value)}
                                    />
                                    <InputError message={form.errors.phone} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Comentarios (opcional)</Label>
                                    <Input id="notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                                </div>
                                {/* Honeypot: hidden from humans, tempting for bots. */}
                                <input
                                    type="text"
                                    name="website"
                                    tabIndex={-1}
                                    autoComplete="off"
                                    value={form.data.website}
                                    onChange={(e) => form.setData('website', e.target.value)}
                                    className="hidden"
                                    aria-hidden="true"
                                />
                            </div>
                            <InputError
                                message={
                                    (form.errors as Record<string, string | undefined>).starts_at ??
                                    (form.errors as Record<string, string | undefined>).service_id ??
                                    (form.errors as Record<string, string | undefined>).staff_id
                                }
                            />
                            <Button
                                type="submit"
                                size="lg"
                                disabled={form.processing}
                                className="w-full bg-[var(--brand)] text-white hover:bg-[var(--brand)]/90 sm:w-auto"
                            >
                                {form.processing && <LoaderCircle className="size-4 animate-spin" />}
                                {deposit > 0 ? 'Continuar al pago de la seña' : 'Confirmar turno'}
                            </Button>
                        </form>
                    )}
                </section>

                <aside className="h-fit space-y-4 rounded-2xl border bg-white p-5 shadow-xs lg:sticky lg:top-6 dark:bg-zinc-900">
                    <h3 className="font-semibold">Tu turno</h3>
                    <dl className="space-y-3 text-sm">
                        <div className="flex items-start gap-2">
                            <Sparkles className="text-muted-foreground mt-0.5 size-4" />
                            <dd>
                                {service ? (
                                    `${service.name} · ${formatMinutes(service.duration_minutes)}`
                                ) : (
                                    <span className="text-muted-foreground">Elegí un servicio</span>
                                )}
                            </dd>
                        </div>
                        <div className="flex items-start gap-2">
                            <UserRound className="text-muted-foreground mt-0.5 size-4" />
                            <dd>{step >= 2 ? staffName(staffId) : <span className="text-muted-foreground">—</span>}</dd>
                        </div>
                        <div className="flex items-start gap-2">
                            <CalendarDays className="text-muted-foreground mt-0.5 size-4" />
                            <dd className="capitalize">
                                {slot && date ? (
                                    `${dayParts(date).long}, ${slot.time} hs`
                                ) : (
                                    <span className="text-muted-foreground normal-case">—</span>
                                )}
                            </dd>
                        </div>
                    </dl>
                    {service && (
                        <div className="space-y-1 border-t pt-4 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Precio</span>
                                <span className="font-semibold">{service.price}</span>
                            </div>
                            {deposit > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Seña ({business.deposit_percent}%)</span>
                                    <span className="font-semibold">
                                        {new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(
                                            deposit / 100,
                                        )}
                                    </span>
                                </div>
                            )}
                        </div>
                    )}
                    <p className="text-muted-foreground flex gap-2 border-t pt-4 text-xs">
                        <ShieldCheck className="size-4 shrink-0" />
                        {deposit > 0
                            ? `Te guardamos el horario ${business.hold_minutes} minutos mientras pagás la seña. Podés cancelar sin cargo hasta ${business.cancellation_notice_hours} h antes.`
                            : `Confirmación inmediata por e-mail. Podés cancelar hasta ${business.cancellation_notice_hours} h antes.`}
                    </p>
                    {slot && (
                        <p className="flex items-center gap-1 text-xs text-emerald-600">
                            <Check className="size-3.5" /> Horario disponible
                        </p>
                    )}
                </aside>
            </div>
        </PublicLayout>
    );
}
