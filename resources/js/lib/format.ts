import type { AppointmentStatus } from '@/types';

export const WEEKDAYS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'] as const;

export function formatLongDate(isoDate: string): string {
    const [y, m, d] = isoDate.split('-').map(Number);
    const label = new Intl.DateTimeFormat('es-AR', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(
        new Date(Date.UTC(y, m - 1, d)),
    );
    return label.charAt(0).toUpperCase() + label.slice(1);
}

export function formatShortDate(isoDate: string): string {
    const [y, m, d] = isoDate.split('-').map(Number);
    return new Intl.DateTimeFormat('es-AR', { day: '2-digit', month: 'short', timeZone: 'UTC' }).format(new Date(Date.UTC(y, m - 1, d)));
}

export function formatMinutes(total: number): string {
    const h = Math.floor(total / 60);
    const m = total % 60;
    if (h === 0) return `${m} min`;
    return m === 0 ? `${h} h` : `${h} h ${m} min`;
}

export function formatPeso(cents: number): string {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: cents % 100 === 0 ? 0 : 2 }).format(
        cents / 100,
    );
}

export const STATUS_STYLES: Record<AppointmentStatus, string> = {
    pending_payment: 'bg-amber-100 text-amber-800 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
    confirmed: 'bg-violet-100 text-violet-800 ring-violet-200 dark:bg-violet-500/15 dark:text-violet-300 dark:ring-violet-500/30',
    completed: 'bg-emerald-100 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
    no_show: 'bg-rose-100 text-rose-800 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30',
    cancelled: 'bg-zinc-100 text-zinc-600 ring-zinc-200 dark:bg-zinc-500/15 dark:text-zinc-400 dark:ring-zinc-500/30',
    expired: 'bg-zinc-100 text-zinc-500 ring-zinc-200 dark:bg-zinc-500/15 dark:text-zinc-500 dark:ring-zinc-500/30',
};

/** Block colours for the agenda timeline. */
export const AGENDA_STYLES: Record<AppointmentStatus, string> = {
    pending_payment: 'border-amber-400 bg-amber-50 text-amber-900 dark:bg-amber-500/15 dark:text-amber-100',
    confirmed: 'border-violet-500 bg-violet-50 text-violet-950 dark:bg-violet-500/20 dark:text-violet-50',
    completed: 'border-emerald-500 bg-emerald-50 text-emerald-950 dark:bg-emerald-500/15 dark:text-emerald-50',
    no_show: 'border-rose-500 bg-rose-50 text-rose-950 dark:bg-rose-500/15 dark:text-rose-50',
    cancelled: 'border-zinc-300 bg-zinc-50 text-zinc-500 line-through dark:bg-zinc-800 dark:text-zinc-400',
    expired: 'border-zinc-300 bg-zinc-50 text-zinc-400 dark:bg-zinc-800',
};

export function todayIn(timeZone: string): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
}

export function addDays(isoDate: string, days: number): string {
    const [y, m, d] = isoDate.split('-').map(Number);
    const date = new Date(Date.UTC(y, m - 1, d + days));
    return date.toISOString().slice(0, 10);
}
