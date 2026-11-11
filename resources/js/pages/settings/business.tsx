import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { NativeSelect } from '@/components/native-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode } from 'react';

interface BusinessSettings {
    name: string;
    slug: string;
    category: string;
    tagline: string | null;
    phone: string | null;
    address: string | null;
    timezone: string;
    slot_interval_minutes: number;
    min_notice_minutes: number;
    booking_horizon_days: number;
    cancellation_notice_hours: number;
    deposit_percent: number;
    hold_minutes: number;
    accent_color: string;
}

interface Props {
    business: BusinessSettings;
    categories: Record<string, string>;
    timezones: string[];
    public_url: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reglas de reserva', href: '/settings/business' }];

function Field({ label, hint, error, children }: { label: string; hint?: string; error?: string; children: ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            {hint && <p className="text-muted-foreground text-xs">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}

export default function BusinessSettingsPage({ business, categories, timezones, public_url }: Props) {
    const { data, setData, patch, processing, errors, recentlySuccessful } = useForm({
        ...business,
        tagline: business.tagline ?? '',
        phone: business.phone ?? '',
        address: business.address ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        patch(route('business.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reglas de reserva" />
            <SettingsLayout>
                <form onSubmit={submit} className="space-y-10">
                    <div className="space-y-6">
                        <HeadingSmall title="Tu negocio" description={`Página pública: ${public_url}`} />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Nombre" error={errors.name}>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            </Field>
                            <Field label="Dirección web (slug)" error={errors.slug} hint="Minúsculas, números y guiones">
                                <Input value={data.slug} onChange={(e) => setData('slug', e.target.value)} />
                            </Field>
                            <Field label="Rubro" error={errors.category}>
                                <NativeSelect value={data.category} onChange={(e) => setData('category', e.target.value)}>
                                    {Object.entries(categories).map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Color de marca" error={errors.accent_color}>
                                <div className="flex gap-2">
                                    <input
                                        type="color"
                                        value={data.accent_color}
                                        onChange={(e) => setData('accent_color', e.target.value)}
                                        className="h-9 w-12 cursor-pointer rounded-md border"
                                    />
                                    <Input value={data.accent_color} onChange={(e) => setData('accent_color', e.target.value)} />
                                </div>
                            </Field>
                            <Field label="Frase" error={errors.tagline}>
                                <Input value={data.tagline} onChange={(e) => setData('tagline', e.target.value)} />
                            </Field>
                            <Field label="Teléfono" error={errors.phone}>
                                <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                            </Field>
                            <div className="sm:col-span-2">
                                <Field label="Dirección" error={errors.address}>
                                    <Input value={data.address} onChange={(e) => setData('address', e.target.value)} />
                                </Field>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <HeadingSmall title="Reglas de reserva" description="Cómo se generan los horarios disponibles." />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Zona horaria" error={errors.timezone}>
                                <NativeSelect value={data.timezone} onChange={(e) => setData('timezone', e.target.value)}>
                                    {timezones.map((tz) => (
                                        <option key={tz} value={tz}>
                                            {tz.replace('America/', '').replaceAll('_', ' ')}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Intervalo entre horarios" error={errors.slot_interval_minutes}>
                                <NativeSelect
                                    value={data.slot_interval_minutes}
                                    onChange={(e) => setData('slot_interval_minutes', Number(e.target.value))}
                                >
                                    {[5, 10, 15, 20, 30, 60].map((m) => (
                                        <option key={m} value={m}>
                                            cada {m} minutos
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field
                                label="Anticipación mínima (minutos)"
                                error={errors.min_notice_minutes}
                                hint="No se ofrecen turnos que empiecen antes de este margen."
                            >
                                <Input
                                    type="number"
                                    min={0}
                                    value={data.min_notice_minutes}
                                    onChange={(e) => setData('min_notice_minutes', Number(e.target.value))}
                                />
                            </Field>
                            <Field label="Horizonte de reserva (días)" error={errors.booking_horizon_days}>
                                <Input
                                    type="number"
                                    min={1}
                                    max={180}
                                    value={data.booking_horizon_days}
                                    onChange={(e) => setData('booking_horizon_days', Number(e.target.value))}
                                />
                            </Field>
                            <Field label="Cancelación online hasta (horas antes)" error={errors.cancellation_notice_hours}>
                                <Input
                                    type="number"
                                    min={0}
                                    value={data.cancellation_notice_hours}
                                    onChange={(e) => setData('cancellation_notice_hours', Number(e.target.value))}
                                />
                            </Field>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <HeadingSmall title="Seña" description="Pedí un porcentaje por adelantado para reducir el ausentismo." />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Porcentaje de seña" error={errors.deposit_percent} hint="0 = sin seña, el turno se confirma al instante.">
                                <div className="flex items-center gap-3">
                                    <input
                                        type="range"
                                        min={0}
                                        max={100}
                                        step={5}
                                        value={data.deposit_percent}
                                        onChange={(e) => setData('deposit_percent', Number(e.target.value))}
                                        className="flex-1 accent-violet-600"
                                    />
                                    <span className="w-12 text-right font-medium tabular-nums">{data.deposit_percent}%</span>
                                </div>
                            </Field>
                            <Field label="Reserva del horario mientras paga (minutos)" error={errors.hold_minutes}>
                                <Input
                                    type="number"
                                    min={5}
                                    max={120}
                                    value={data.hold_minutes}
                                    onChange={(e) => setData('hold_minutes', Number(e.target.value))}
                                />
                            </Field>
                        </div>
                    </div>

                    <div className="flex items-center gap-4">
                        <Button disabled={processing}>Guardar cambios</Button>
                        {recentlySuccessful && <p className="text-sm text-emerald-600">Guardado</p>}
                    </div>
                </form>
            </SettingsLayout>
        </AppLayout>
    );
}
