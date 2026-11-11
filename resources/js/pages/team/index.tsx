import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { WEEKDAYS } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Service } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, UserPlus, X } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Range = { weekday: number; starts_at: string; ends_at: string };

interface Member {
    id: number;
    name: string;
    title: string | null;
    email: string;
    role: 'owner' | 'staff';
    role_label: string;
    is_bookable: boolean;
    service_ids: number[];
    working_hours: Range[];
}

interface Props {
    members: Member[];
    services: Service[];
}

type MemberForm = {
    name: string;
    title: string;
    email: string;
    password: string;
    is_bookable: boolean;
    service_ids: number[];
    working_hours: Range[];
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Equipo', href: '/equipo' }];

const summarize = (ranges: Range[]) =>
    WEEKDAYS.map((label, i) => {
        const day = ranges.filter((r) => r.weekday === i + 1);
        return { label: label.slice(0, 3), text: day.map((r) => `${r.starts_at}–${r.ends_at}`).join(', ') };
    });

export default function TeamIndex({ members, services }: Props) {
    const [editing, setEditing] = useState<Member | 'new' | null>(null);
    const form = useForm<MemberForm>({ name: '', title: '', email: '', password: '', is_bookable: true, service_ids: [], working_hours: [] });

    const open = (member: Member | 'new') => {
        form.clearErrors();
        form.setData(
            member === 'new'
                ? {
                      name: '',
                      title: '',
                      email: '',
                      password: '',
                      is_bookable: true,
                      service_ids: [],
                      working_hours: [1, 2, 3, 4, 5].map((d) => ({ weekday: d, starts_at: '09:00', ends_at: '18:00' })),
                  }
                : {
                      name: member.name,
                      title: member.title ?? '',
                      email: member.email,
                      password: '',
                      is_bookable: member.is_bookable,
                      service_ids: member.service_ids,
                      working_hours: member.working_hours,
                  },
        );
        setEditing(member);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };
        if (editing === 'new') form.post(route('team.store'), options);
        else if (editing) form.put(route('team.update', editing.id), options);
    };

    const setRange = (index: number, patch: Partial<Range>) =>
        form.setData(
            'working_hours',
            form.data.working_hours.map((r, i) => (i === index ? { ...r, ...patch } : r)),
        );

    const addRange = (weekday: number) =>
        form.setData('working_hours', [...form.data.working_hours, { weekday, starts_at: '14:00', ends_at: '18:00' }]);
    const removeRange = (index: number) =>
        form.setData(
            'working_hours',
            form.data.working_hours.filter((_, i) => i !== index),
        );
    const toggleService = (id: number) =>
        form.setData(
            'service_ids',
            form.data.service_ids.includes(id) ? form.data.service_ids.filter((s) => s !== id) : [...form.data.service_ids, id],
        );

    const remove = (member: Member) => {
        if (window.confirm(`¿Eliminar a ${member.name} del equipo?`)) router.delete(route('team.destroy', member.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Equipo" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Equipo"
                    description="Profesionales, sus horarios (con cortes) y qué servicios realizan."
                    actions={
                        <Button onClick={() => open('new')}>
                            <UserPlus className="size-4" /> Agregar profesional
                        </Button>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    {members.map((m) => (
                        <article key={m.id} className="bg-card rounded-xl border p-5 shadow-xs">
                            <div className="flex items-start gap-3">
                                <span className="flex size-11 items-center justify-center rounded-full bg-violet-100 font-semibold text-violet-700 dark:bg-violet-500/20 dark:text-violet-200">
                                    {m.name
                                        .split(' ')
                                        .slice(0, 2)
                                        .map((p) => p[0])
                                        .join('')}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="font-semibold">{m.name}</h2>
                                        <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-xs dark:bg-zinc-800">{m.role_label}</span>
                                        {!m.is_bookable && <span className="text-muted-foreground text-xs">(no recibe turnos)</span>}
                                    </div>
                                    <p className="text-muted-foreground truncate text-sm">
                                        {m.title ?? 'Profesional'} · {m.email}
                                    </p>
                                </div>
                                <div className="flex gap-1">
                                    <Button size="icon" variant="ghost" onClick={() => open(m)} aria-label="Editar">
                                        <Pencil className="size-4" />
                                    </Button>
                                    {m.role !== 'owner' && (
                                        <Button size="icon" variant="ghost" onClick={() => remove(m)} aria-label="Eliminar">
                                            <Trash2 className="size-4" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                            <dl className="mt-4 grid grid-cols-7 gap-1 text-center text-xs">
                                {summarize(m.working_hours).map((d) => (
                                    <div
                                        key={d.label}
                                        className={cn(
                                            'rounded-md px-1 py-2',
                                            d.text ? 'bg-violet-50 dark:bg-violet-500/10' : 'bg-zinc-50 dark:bg-zinc-900',
                                        )}
                                    >
                                        <dt className="font-medium">{d.label}</dt>
                                        <dd className="text-muted-foreground mt-1 leading-tight break-words">
                                            {d.text ? d.text.replaceAll(', ', '\n') : '—'}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            <p className="text-muted-foreground mt-3 text-xs">
                                {services
                                    .filter((s) => m.service_ids.includes(s.id))
                                    .map((s) => s.name)
                                    .join(' · ') || 'Sin servicios asignados'}
                            </p>
                        </article>
                    ))}
                </div>
            </div>

            <Sheet open={editing !== null} onOpenChange={(o) => !o && setEditing(null)}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                    <form onSubmit={submit} className="flex min-h-full flex-col">
                        <SheetHeader>
                            <SheetTitle>{editing === 'new' ? 'Nuevo profesional' : 'Editar profesional'}</SheetTitle>
                            <SheetDescription>
                                Los horarios son en la zona horaria del negocio. Agregá varias franjas por día para modelar cortes.
                            </SheetDescription>
                        </SheetHeader>
                        <div className="flex-1 space-y-5 px-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nombre</Label>
                                    <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="title">Especialidad</Label>
                                    <Input
                                        id="title"
                                        placeholder="Barbero, Kinesióloga…"
                                        value={form.data.title}
                                        onChange={(e) => form.setData('title', e.target.value)}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">E-mail</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={form.data.email}
                                        onChange={(e) => form.setData('email', e.target.value)}
                                        required
                                    />
                                    <InputError message={form.errors.email} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="password">{editing === 'new' ? 'Contraseña inicial' : 'Nueva contraseña'}</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        autoComplete="new-password"
                                        placeholder={editing === 'new' ? '' : 'Dejar vacío para no cambiar'}
                                        value={form.data.password}
                                        onChange={(e) => form.setData('password', e.target.value)}
                                    />
                                    <InputError message={form.errors.password} />
                                </div>
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={form.data.is_bookable} onCheckedChange={(v) => form.setData('is_bookable', v === true)} /> Recibe
                                turnos online
                            </label>

                            <div className="space-y-2">
                                <Label>Horarios</Label>
                                <InputError message={form.errors.working_hours} />
                                <div className="divide-y rounded-lg border">
                                    {WEEKDAYS.map((label, i) => {
                                        const weekday = i + 1;
                                        const ranges = form.data.working_hours
                                            .map((r, index) => ({ ...r, index }))
                                            .filter((r) => r.weekday === weekday);
                                        return (
                                            <div key={label} className="flex items-start gap-3 p-3">
                                                <span className="w-20 pt-2 text-sm font-medium">{label}</span>
                                                <div className="flex-1 space-y-2">
                                                    {ranges.length === 0 && <p className="text-muted-foreground pt-2 text-sm">No trabaja</p>}
                                                    {ranges.map((r) => (
                                                        <div key={r.index} className="flex items-center gap-2">
                                                            <Input
                                                                type="time"
                                                                step={300}
                                                                value={r.starts_at}
                                                                onChange={(e) => setRange(r.index, { starts_at: e.target.value })}
                                                                className="w-28"
                                                            />
                                                            <span className="text-muted-foreground">a</span>
                                                            <Input
                                                                type="time"
                                                                step={300}
                                                                value={r.ends_at}
                                                                onChange={(e) => setRange(r.index, { ends_at: e.target.value })}
                                                                className="w-28"
                                                            />
                                                            <Button
                                                                type="button"
                                                                size="icon"
                                                                variant="ghost"
                                                                onClick={() => removeRange(r.index)}
                                                                aria-label="Quitar franja"
                                                            >
                                                                <X className="size-4" />
                                                            </Button>
                                                        </div>
                                                    ))}
                                                </div>
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    onClick={() => addRange(weekday)}
                                                    aria-label={`Agregar franja el ${label}`}
                                                >
                                                    <Plus className="size-4" />
                                                </Button>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Servicios que realiza</Label>
                                <div className="flex flex-wrap gap-2">
                                    {services.map((s) => (
                                        <label
                                            key={s.id}
                                            className={cn(
                                                'flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm',
                                                form.data.service_ids.includes(s.id) && 'border-violet-500 bg-violet-50 dark:bg-violet-500/10',
                                            )}
                                        >
                                            <Checkbox checked={form.data.service_ids.includes(s.id)} onCheckedChange={() => toggleService(s.id)} />
                                            {s.name}
                                        </label>
                                    ))}
                                </div>
                            </div>
                        </div>
                        <SheetFooter className="flex-row justify-end gap-2">
                            <Button type="button" variant="ghost" onClick={() => setEditing(null)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Guardar
                            </Button>
                        </SheetFooter>
                    </form>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
