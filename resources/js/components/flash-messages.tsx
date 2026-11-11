import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, X, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

/** Toast for the `flash.success` / `flash.error` session messages shared by Laravel. */
export function FlashMessages() {
    const { flash } = usePage<SharedData>().props;
    const [visible, setVisible] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

    useEffect(() => {
        if (flash?.error) setVisible({ type: 'error', text: flash.error });
        else if (flash?.success) setVisible({ type: 'success', text: flash.success });
        else return;

        const timer = window.setTimeout(() => setVisible(null), 5000);
        return () => window.clearTimeout(timer);
    }, [flash?.success, flash?.error]);

    if (!visible) return null;

    const Icon = visible.type === 'success' ? CheckCircle2 : XCircle;

    return (
        <div className="pointer-events-none fixed inset-x-0 top-4 z-50 flex justify-center px-4" role="status" aria-live="polite">
            <div
                className={cn(
                    'animate-in fade-in slide-in-from-top-2 pointer-events-auto flex max-w-md items-start gap-3 rounded-xl border bg-white px-4 py-3 text-sm shadow-lg dark:bg-zinc-900',
                    visible.type === 'success' ? 'border-emerald-200 dark:border-emerald-500/30' : 'border-rose-200 dark:border-rose-500/30',
                )}
            >
                <Icon className={cn('mt-0.5 size-4 shrink-0', visible.type === 'success' ? 'text-emerald-600' : 'text-rose-600')} />
                <p className="flex-1">{visible.text}</p>
                <button type="button" onClick={() => setVisible(null)} className="text-muted-foreground hover:text-foreground" aria-label="Cerrar">
                    <X className="size-4" />
                </button>
            </div>
        </div>
    );
}
