import { NativeSelect } from '@/components/native-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatShortDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type Appointment, type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Props {
    appointments: Paginated<Appointment>;
    filters: { status?: string; staff_id?: string; from?: string; to?: string; q?: string };
    statuses: { value: string; label: string }[];
    staff: { id: number; name: string }[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Turnos', href: '/turnos' }];

export default function AppointmentsIndex({ appointments, filters, statuses, staff }: Props) {
    const [form, setForm] = useState(filters);

    const apply = (e?: FormEvent, next = form) => {
        e?.preventDefault();
        const clean = Object.fromEntries(Object.entries(next).filter(([, v]) => v !== '' && v !== undefined));
        router.get('/turnos', clean, { preserveState: true, preserveScroll: true });
    };

    const update = (key: keyof Props['filters'], value: string) => {
        const next = { ...form, [key]: value };
        setForm(next);
        if (key !== 'q') apply(undefined, next);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Turnos" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Turnos" description={`${appointments.meta.total} resultados`} />

                <form onSubmit={apply} className="bg-card grid gap-3 rounded-xl border p-4 shadow-xs md:grid-cols-6">
                    <div className="relative md:col-span-2">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                        <Input
                            placeholder="Buscar cliente o e-mail…"
                            className="pl-9"
                            value={form.q ?? ''}
                            onChange={(e) => setForm({ ...form, q: e.target.value })}
                        />
                    </div>
                    <NativeSelect value={form.status ?? ''} onChange={(e) => update('status', e.target.value)} aria-label="Estado">
                        <option value="">Todos los estados</option>
                        {statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </NativeSelect>
                    {staff.length > 0 ? (
                        <NativeSelect value={form.staff_id ?? ''} onChange={(e) => update('staff_id', e.target.value)} aria-label="Profesional">
                            <option value="">Todo el equipo</option>
                            {staff.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </NativeSelect>
                    ) : (
                        <div />
                    )}
                    <Input type="date" value={form.from ?? ''} onChange={(e) => update('from', e.target.value)} aria-label="Desde" />
                    <Input type="date" value={form.to ?? ''} onChange={(e) => update('to', e.target.value)} aria-label="Hasta" />
                </form>

                <div className="bg-card overflow-hidden rounded-xl border shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-muted-foreground text-left text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Fecha</th>
                                    <th className="px-4 py-3 font-medium">Cliente</th>
                                    <th className="px-4 py-3 font-medium">Servicio</th>
                                    <th className="px-4 py-3 font-medium">Profesional</th>
                                    <th className="px-4 py-3 text-right font-medium">Precio</th>
                                    <th className="px-4 py-3 font-medium">Estado</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {appointments.data.map((a) => (
                                    <tr key={a.id} className="hover:bg-muted/30">
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <Link href={`/agenda?date=${a.local_date}`} className="font-medium hover:underline">
                                                {formatShortDate(a.local_date)} · {a.local_start}
                                            </Link>
                                            <p className="text-muted-foreground text-xs">{a.reference}</p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <p className="font-medium">{a.customer?.name}</p>
                                            <p className="text-muted-foreground text-xs">{a.customer?.email}</p>
                                        </td>
                                        <td className="px-4 py-3">{a.service?.name}</td>
                                        <td className="px-4 py-3">{a.staff?.name}</td>
                                        <td className="px-4 py-3 text-right tabular-nums">
                                            {a.price}
                                            {a.deposit_cents > 0 && <p className="text-muted-foreground text-xs">seña {a.deposit}</p>}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={a.status} label={a.status_label} />
                                        </td>
                                    </tr>
                                ))}
                                {appointments.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="text-muted-foreground px-4 py-10 text-center">
                                            No hay turnos con esos filtros.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {appointments.meta.last_page > 1 && (
                        <nav className="flex flex-wrap items-center justify-center gap-1 border-t p-3">
                            {appointments.meta.links.map((link, i) => (
                                <Button
                                    key={i}
                                    size="sm"
                                    variant={link.active ? 'default' : 'ghost'}
                                    disabled={!link.url}
                                    asChild={!!link.url}
                                    className={cn(!link.url && 'opacity-50')}
                                >
                                    {link.url ? (
                                        <Link href={link.url} preserveScroll dangerouslySetInnerHTML={{ __html: link.label }} />
                                    ) : (
                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                    )}
                                </Button>
                            ))}
                        </nav>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
