import { Button } from '@/components/ui/button';
import { Head, router } from '@inertiajs/react';
import { CreditCard, FlaskConical, Lock } from 'lucide-react';
import { useState } from 'react';

interface Props {
    checkout: { id: string; status: string; amount: string; merchant: string; concept: string; expires_at: string | null };
}

/** Hosted checkout of the sandbox provider. Deliberately styled unlike Turnero: it is "another company". */
export default function PagoSimuladoCheckout({ checkout }: Props) {
    const [sending, setSending] = useState<'approved' | 'rejected' | null>(null);
    const processing = sending !== null;

    const pay = (outcome: 'approved' | 'rejected') => {
        setSending(outcome);
        router.post(route('pagosimulado.complete', checkout.id), { outcome }, { onFinish: () => setSending(null) });
    };

    const pending = checkout.status === 'pending';

    return (
        <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-sky-500 to-cyan-400 p-4">
            <Head title="PagoSimulado · Checkout" />
            <div className="w-full max-w-md overflow-hidden rounded-3xl bg-white text-zinc-900 shadow-2xl">
                <div className="flex items-center justify-between bg-zinc-900 px-6 py-4 text-white">
                    <span className="flex items-center gap-2 font-bold tracking-tight">
                        <CreditCard className="size-5 text-cyan-300" /> PagoSimulado
                    </span>
                    <span className="flex items-center gap-1 rounded-full bg-amber-400/20 px-2 py-0.5 text-xs text-amber-200">
                        <FlaskConical className="size-3.5" /> Sandbox
                    </span>
                </div>
                <div className="space-y-6 p-6">
                    <div>
                        <p className="text-sm text-zinc-500">Pagás a</p>
                        <p className="text-lg font-semibold">{checkout.merchant}</p>
                        <p className="text-sm text-zinc-500">{checkout.concept}</p>
                    </div>
                    <div className="rounded-2xl bg-zinc-50 p-5 text-center">
                        <p className="text-sm text-zinc-500">Total</p>
                        <p className="text-4xl font-bold tracking-tight tabular-nums">{checkout.amount}</p>
                    </div>
                    {pending ? (
                        <div className="space-y-3">
                            <Button
                                size="lg"
                                className="w-full bg-sky-600 text-white hover:bg-sky-700"
                                disabled={processing}
                                onClick={() => pay('approved')}
                            >
                                {sending === 'approved' ? 'Procesando…' : 'Aprobar pago'}
                            </Button>
                            <Button size="lg" variant="outline" className="w-full" disabled={processing} onClick={() => pay('rejected')}>
                                Simular rechazo
                            </Button>
                        </div>
                    ) : (
                        <p className="rounded-xl bg-zinc-100 p-4 text-center text-sm">Este checkout ya fue procesado ({checkout.status}).</p>
                    )}
                    <p className="flex items-center justify-center gap-1 text-xs text-zinc-400">
                        <Lock className="size-3" /> Entorno de pruebas: no se mueve dinero real. El resultado llega a Turnero por webhook firmado.
                    </p>
                </div>
            </div>
        </div>
    );
}
