import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatMinutes } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Service, type SharedData, type StaffMember } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Clock, Pencil, Plus, Timer, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Props {
    services: Service[];
    staff: StaffMember[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Servicios', href: '/servicios' }];

type ServiceForm = {
    name: string;
    description: string;
    duration_minutes: number;
    buffer_minutes: number;
    price: string;
    is_active: boolean;
    staff_ids: number[];
};

export default function ServicesIndex({ services, staff }: Props) {
    const { auth } = usePage<SharedData>().props;
    const [editing, setEditing] = useState<Service | 'new' | null>(null);
    const form = useForm<ServiceForm>({
        name: '',
        description: '',
        duration_minutes: 30,
        buffer_minutes: 0,
        price: '',
        is_active: true,
        staff_ids: [],
    });

    const open = (service: Service | 'new') => {
        form.clearErrors();
        form.setData(
            service === 'new'
                ? {
                      name: '',
                      description: '',
                      duration_minutes: 30,
                      buffer_minutes: 0,
                      price: '',
                      is_active: true,
                      staff_ids: staff.map((s) => s.id),
                  }
                : {
                      name: service.name,
                      description: service.description ?? '',
                      duration_minutes: service.duration_minutes,
                      buffer_minutes: service.buffer_minutes,
                      price: String(service.price_cents / 100),
                      is_active: service.is_active,
                      staff_ids: service.staff_ids ?? [],
                  },
        );
        setEditing(service);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };
        if (editing === 'new') form.post(route('services.store'), options);
        else if (editing) form.put(route('services.update', editing.id), options);
    };

    const toggleStaff = (id: number) =>
        form.setData('staff_ids', form.data.staff_ids.includes(id) ? form.data.staff_ids.filter((s) => s !== id) : [...form.data.staff_ids, id]);

    const remove = (service: Service) => {
        if (window.confirm(`¿Eliminar «${service.name}»?`)) router.delete(route('services.destroy', service.id), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Servicios" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Servicios"
                    description="Lo que tus clientes pueden reservar. La duración y el tiempo de preparación definen los horarios ofrecidos."
                    actions={
                        auth.user.is_owner && (
                            <Button onClick={() => open('new')}>
                                <Plus className="size-4" /> Nuevo servicio
                            </Button>
                        )
                    }
                />

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {services.map((service) => (
                        <article
                            key={service.id}
                            className={cn('bg-card flex flex-col rounded-xl border p-5 shadow-xs', !service.is_active && 'opacity-60')}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">{service.name}</h2>
                                    {!service.is_active && <span className="text-muted-foreground text-xs">Inactivo</span>}
                                </div>
                                <p className="text-lg font-semibold text-violet-700 tabular-nums dark:text-violet-300">{service.price}</p>
                            </div>
                            {service.description && <p className="text-muted-foreground mt-2 line-clamp-2 text-sm">{service.description}</p>}
                            <div className="text-muted-foreground mt-4 flex flex-wrap gap-3 text-xs">
                                <span className="flex items-center gap-1">
                                    <Clock className="size-3.5" /> {formatMinutes(service.duration_minutes)}
                                </span>
                                {service.buffer_minutes > 0 && (
                                    <span className="flex items-center gap-1">
                                        <Timer className="size-3.5" /> +{service.buffer_minutes} min de preparación
                                    </span>
                                )}
                            </div>
                            <div className="mt-4 flex items-center justify-between border-t pt-4">
                                <div className="flex -space-x-2">
                                    {staff
                                        .filter((s) => service.staff_ids?.includes(s.id))
                                        .map((s) => (
                                            <span
                                                key={s.id}
                                                title={s.name}
                                                className="border-card flex size-7 items-center justify-center rounded-full border-2 bg-violet-100 text-[10px] font-semibold text-violet-700 dark:bg-violet-500/20 dark:text-violet-200"
                                            >
                                                {s.initials}
                                            </span>
                                        ))}
                                </div>
                                {auth.user.is_owner && (
                                    <div className="flex gap-1">
                                        <Button size="icon" variant="ghost" onClick={() => open(service)} aria-label="Editar">
                                            <Pencil className="size-4" />
                                        </Button>
                                        <Button size="icon" variant="ghost" onClick={() => remove(service)} aria-label="Eliminar">
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </article>
                    ))}
                </div>
            </div>

            <Dialog open={editing !== null} onOpenChange={(o) => !o && setEditing(null)}>
                <DialogContent className="sm:max-w-lg">
                    <form onSubmit={submit} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>{editing === 'new' ? 'Nuevo servicio' : 'Editar servicio'}</DialogTitle>
                            <DialogDescription>Duraciones en múltiplos de 5 minutos.</DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nombre</Label>
                            <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="description">Descripción</Label>
                            <Input id="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                        </div>
                        <div className="grid grid-cols-3 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="duration">Duración (min)</Label>
                                <Input
                                    id="duration"
                                    type="number"
                                    step={5}
                                    min={5}
                                    value={form.data.duration_minutes}
                                    onChange={(e) => form.setData('duration_minutes', Number(e.target.value))}
                                />
                                <InputError message={form.errors.duration_minutes} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="buffer">Preparación</Label>
                                <Input
                                    id="buffer"
                                    type="number"
                                    step={5}
                                    min={0}
                                    value={form.data.buffer_minutes}
                                    onChange={(e) => form.setData('buffer_minutes', Number(e.target.value))}
                                />
                                <InputError message={form.errors.buffer_minutes} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="price">Precio ($)</Label>
                                <Input
                                    id="price"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={form.data.price}
                                    onChange={(e) => form.setData('price', e.target.value)}
                                    required
                                />
                                <InputError message={form.errors.price} />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label>¿Quién lo realiza?</Label>
                            <div className="flex flex-wrap gap-2">
                                {staff.map((s) => (
                                    <label
                                        key={s.id}
                                        className={cn(
                                            'flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm',
                                            form.data.staff_ids.includes(s.id) && 'border-violet-500 bg-violet-50 dark:bg-violet-500/10',
                                        )}
                                    >
                                        <Checkbox checked={form.data.staff_ids.includes(s.id)} onCheckedChange={() => toggleStaff(s.id)} />
                                        {s.name}
                                    </label>
                                ))}
                            </div>
                            <InputError message={form.errors.staff_ids} />
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox checked={form.data.is_active} onCheckedChange={(v) => form.setData('is_active', v === true)} /> Disponible para
                            reservar online
                        </label>
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setEditing(null)}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Guardar
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
