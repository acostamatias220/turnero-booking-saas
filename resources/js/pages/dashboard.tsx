import { BarChart } from '@/components/bar-chart';
import { StatCard } from '@/components/stat-card';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatShortDate } from '@/lib/format';
import { type Appointment, type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarCheck, Check, Copy, DollarSign, Gauge, UserX } from 'lucide-react';
import { useState } from 'react';

interface Props {
    kpis: { today: number; week_revenue: string; occupancy: number; no_show_rate: number };
    week: { date: string; label: string; total: number }[];
    top_services: { name: string; total: number }[];
    upcoming: Appointment[];
    public_url: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Resumen', href: '/dashboard' }];

export default function Dashboard({ kpis, week, top_services, upcoming, public_url }: Props) {
    const { auth } = usePage<SharedData>().props;
    const [copied, setCopied] = useState(false);
    const topMax = Math.max(1, ...top_services.map((s) => s.total));

    const copy = async () => {
        await navigator.clipboard?.writeText(public_url);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Resumen" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Hola, {auth.user.name.split(' ')[0]} 👋</h1>
                        <p className="text-muted-foreground text-sm">
                            {auth.user.is_owner ? 'Así viene tu negocio esta semana.' : 'Así viene tu agenda esta semana.'}
                        </p>
                    </div>
                    <div className="bg-card flex items-center gap-2 rounded-lg border py-1.5 pr-1.5 pl-3 text-sm shadow-xs">
                        <span className="text-muted-foreground hidden sm:inline">Tu página de reservas:</span>
                        <a
                            href={public_url}
                            target="_blank"
                            rel="noreferrer"
                            className="max-w-56 truncate font-medium text-violet-700 hover:underline dark:text-violet-300"
                        >
                            {public_url.replace(/^https?:\/\//, '')}
                        </a>
                        <Button size="sm" variant="ghost" onClick={copy} aria-label="Copiar enlace">
                            {copied ? <Check className="size-4 text-emerald-600" /> : <Copy className="size-4" />}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Turnos de hoy" value={kpis.today} icon={CalendarCheck} hint="Confirmados, pendientes y atendidos" />
                    <StatCard
                        label="Facturación semanal"
                        value={kpis.week_revenue}
                        icon={DollarSign}
                        tone="emerald"
                        hint="Turnos confirmados y completados"
                    />
                    <StatCard
                        label="Ocupación semanal"
                        value={`${kpis.occupancy}%`}
                        icon={Gauge}
                        tone="sky"
                        hint="Minutos reservados / horario laboral"
                    />
                    <StatCard
                        label="Ausentismo (30 días)"
                        value={`${kpis.no_show_rate}%`}
                        icon={UserX}
                        tone="rose"
                        hint="No asistió / turnos pasados"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-5">
                    <section className="bg-card rounded-xl border p-5 shadow-xs lg:col-span-3">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="font-medium">Próximos 7 días</h2>
                            <span className="text-muted-foreground text-xs">turnos activos por día</span>
                        </div>
                        <BarChart data={week.map((d) => ({ label: d.label, value: d.total }))} />
                    </section>

                    <section className="bg-card rounded-xl border p-5 shadow-xs lg:col-span-2">
                        <h2 className="mb-4 font-medium">Servicios más pedidos</h2>
                        {top_services.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Todavía no hay datos de los últimos 30 días.</p>
                        ) : (
                            <ul className="space-y-3">
                                {top_services.map((s) => (
                                    <li key={s.name}>
                                        <div className="mb-1 flex justify-between text-sm">
                                            <span>{s.name}</span>
                                            <span className="text-muted-foreground tabular-nums">{s.total}</span>
                                        </div>
                                        <div className="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                            <div className="h-full rounded-full bg-violet-500" style={{ width: `${(s.total / topMax) * 100}%` }} />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </div>

                <section className="bg-card rounded-xl border shadow-xs">
                    <div className="flex items-center justify-between border-b px-5 py-4">
                        <h2 className="font-medium">Próximos turnos</h2>
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/agenda">Ver agenda</Link>
                        </Button>
                    </div>
                    {upcoming.length === 0 ? (
                        <p className="text-muted-foreground px-5 py-8 text-center text-sm">
                            No hay turnos próximos. Compartí tu página de reservas 🙂
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {upcoming.map((a) => (
                                <li key={a.id} className="flex items-center gap-4 px-5 py-3">
                                    <div className="w-16 shrink-0 text-center">
                                        <p className="text-muted-foreground text-xs uppercase">{formatShortDate(a.local_date)}</p>
                                        <p className="font-semibold tabular-nums">{a.local_start}</p>
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">{a.customer?.name}</p>
                                        <p className="text-muted-foreground truncate text-sm">
                                            {a.service?.name} · con {a.staff?.name}
                                        </p>
                                    </div>
                                    <StatusBadge status={a.status} label={a.status_label} />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
