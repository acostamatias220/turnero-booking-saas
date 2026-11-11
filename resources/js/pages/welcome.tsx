import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, BellRing, CalendarCheck2, ShieldCheck, Timer, Users, Wallet } from 'lucide-react';

interface Demo {
    name: string;
    slug: string;
    category: string;
    tagline: string | null;
    accent_color: string;
}

const FEATURES = [
    {
        icon: CalendarCheck2,
        title: 'Sin turnos superpuestos',
        text: 'Disponibilidad en tiempo real y una garantía a nivel base de datos: dos personas nunca se quedan con el mismo horario.',
    },
    {
        icon: Wallet,
        title: 'Señas online',
        text: 'Pedí un % por adelantado. El horario se reserva mientras pagan y se libera solo si no se completa.',
    },
    {
        icon: BellRing,
        title: 'Recordatorios automáticos',
        text: 'Confirmación con archivo de calendario, recordatorio 24 h antes y link para cancelar.',
    },
    { icon: Users, title: 'Equipo y horarios', text: 'Cada profesional con sus servicios, cortes de horario, vacaciones y feriados nacionales.' },
    { icon: Timer, title: 'Reglas a tu medida', text: 'Intervalos, anticipación mínima, tiempo de limpieza entre turnos y política de cancelación.' },
    { icon: ShieldCheck, title: 'Datos aislados', text: 'Cada negocio ve solo lo suyo. API REST con tokens para integrar con lo que ya usás.' },
];

export default function Welcome({ demo }: { demo: Demo[] }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Turnos online para tu negocio" />
            <div className="min-h-screen bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
                <header className="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                    <Link href="/" className="flex items-center gap-2 text-lg font-bold tracking-tight">
                        <span className="flex size-8 items-center justify-center rounded-lg bg-violet-600 text-white">
                            <AppLogoIcon className="size-5 fill-current" />
                        </span>
                        Turnero
                    </Link>
                    <nav className="flex items-center gap-2">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={route('dashboard')}>Ir al panel</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost">
                                    <Link href={route('login')}>Ingresar</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={route('register')}>Crear cuenta gratis</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <section className="relative overflow-hidden">
                    <div className="pointer-events-none absolute inset-x-0 -top-40 -z-0 mx-auto h-[480px] max-w-4xl rounded-full bg-gradient-to-r from-violet-300/40 via-fuchsia-300/30 to-sky-300/40 blur-3xl dark:from-violet-700/30 dark:via-fuchsia-700/20 dark:to-sky-700/20" />
                    <div className="relative mx-auto max-w-4xl px-6 pt-16 pb-20 text-center md:pt-24">
                        <span className="inline-flex items-center gap-2 rounded-full border bg-white/70 px-3 py-1 text-xs font-medium backdrop-blur dark:bg-zinc-900/70">
                            <span className="size-1.5 rounded-full bg-emerald-500" /> Para barberías, consultorios, estudios y más
                        </span>
                        <h1 className="mt-6 text-4xl font-bold tracking-tight text-balance md:text-6xl">
                            Tu agenda llena,{' '}
                            <span className="bg-gradient-to-r from-violet-600 to-fuchsia-500 bg-clip-text text-transparent">sin WhatsApps</span> a las
                            11 de la noche.
                        </h1>
                        <p className="text-muted-foreground mx-auto mt-5 max-w-2xl text-lg text-balance">
                            Turnero le da a tu negocio una página de reservas online con señas, recordatorios y un panel para todo tu equipo.
                        </p>
                        <div className="mt-8 flex flex-wrap justify-center gap-3">
                            <Button asChild size="lg">
                                <Link href={route('register')}>
                                    Empezar ahora <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                            {demo[0] && (
                                <Button asChild size="lg" variant="outline">
                                    <Link href={route('public.booking', demo[0].slug)}>Probar una reserva</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </section>

                <section className="mx-auto grid max-w-6xl gap-4 px-6 pb-20 sm:grid-cols-2 lg:grid-cols-3">
                    {FEATURES.map(({ icon: Icon, title, text }) => (
                        <div key={title} className="rounded-2xl border p-6">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                                <Icon className="size-5" />
                            </span>
                            <h3 className="mt-4 font-semibold">{title}</h3>
                            <p className="text-muted-foreground mt-1 text-sm">{text}</p>
                        </div>
                    ))}
                </section>

                {demo.length > 0 && (
                    <section className="border-t bg-zinc-50 dark:bg-zinc-900/40">
                        <div className="mx-auto max-w-6xl px-6 py-16">
                            <h2 className="text-2xl font-bold tracking-tight">Negocios de demo</h2>
                            <p className="text-muted-foreground mt-1">
                                Reservá un turno como cliente, o entrá al panel con las credenciales de demo.
                            </p>
                            <div className="mt-8 grid gap-4 md:grid-cols-3">
                                {demo.map((b) => (
                                    <Link
                                        key={b.slug}
                                        href={route('public.booking', b.slug)}
                                        className="group overflow-hidden rounded-2xl border bg-white transition hover:-translate-y-0.5 hover:shadow-lg dark:bg-zinc-900"
                                    >
                                        <div className="h-2" style={{ background: b.accent_color }} />
                                        <div className="p-5">
                                            <p className="font-semibold">{b.name}</p>
                                            <p className="text-muted-foreground text-sm">{b.tagline}</p>
                                            <p className="mt-4 flex items-center gap-1 text-sm font-medium" style={{ color: b.accent_color }}>
                                                Reservar <ArrowRight className="size-4 transition group-hover:translate-x-0.5" />
                                            </p>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                            <p className="text-muted-foreground mt-6 text-sm">
                                Panel de demo: <code className="rounded bg-zinc-200 px-1.5 py-0.5 dark:bg-zinc-800">tano@turnero.test</code> /{' '}
                                <code className="rounded bg-zinc-200 px-1.5 py-0.5 dark:bg-zinc-800">password</code> (solo datos de prueba).
                            </p>
                        </div>
                    </section>
                )}

                <footer className="text-muted-foreground mx-auto max-w-6xl px-6 py-8 text-center text-sm">
                    Turnero · proyecto de portfolio de Matías Acosta
                </footer>
            </div>
        </>
    );
}
