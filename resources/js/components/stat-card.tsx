import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';

export function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'violet',
}: {
    label: string;
    value: string | number;
    hint?: string;
    icon: LucideIcon;
    tone?: 'violet' | 'emerald' | 'sky' | 'rose';
}) {
    const tones = {
        violet: 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
        emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        sky: 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        rose: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
    };

    return (
        <div className="bg-card rounded-xl border p-5 shadow-xs">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-muted-foreground text-sm">{label}</p>
                    <p className="mt-1 text-2xl font-semibold tracking-tight">{value}</p>
                    {hint && <p className="text-muted-foreground mt-1 text-xs">{hint}</p>}
                </div>
                <span className={cn('flex size-10 items-center justify-center rounded-lg', tones[tone])}>
                    <Icon className="size-5" />
                </span>
            </div>
        </div>
    );
}
