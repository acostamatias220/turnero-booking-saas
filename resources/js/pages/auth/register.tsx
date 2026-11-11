import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { NativeSelect } from '@/components/native-select';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface RegisterForm {
    business_name: string;
    category: string;
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    [key: string]: string;
}

export default function Register({ categories }: { categories: Record<string, string> }) {
    const { data, setData, post, processing, errors, reset } = useForm<RegisterForm>({
        business_name: '',
        category: Object.keys(categories)[0] ?? '',
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const field = (key: keyof RegisterForm, label: string, props: React.ComponentProps<typeof Input> = {}) => (
        <div className="grid gap-2">
            <Label htmlFor={key as string}>{label}</Label>
            <Input id={key as string} required value={data[key]} onChange={(e) => setData(key, e.target.value)} disabled={processing} {...props} />
            <InputError message={errors[key]} />
        </div>
    );

    return (
        <AuthLayout title="Creá tu cuenta" description="Tu página de reservas online en menos de un minuto">
            <Head title="Registrarse" />
            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-5">
                    {field('business_name', 'Nombre del negocio', { autoFocus: true, placeholder: 'Barbería El Tano' })}
                    <div className="grid gap-2">
                        <Label htmlFor="category">Rubro</Label>
                        <NativeSelect id="category" value={data.category} onChange={(e) => setData('category', e.target.value)} disabled={processing}>
                            {Object.entries(categories).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </NativeSelect>
                        <InputError message={errors.category} />
                    </div>
                    {field('name', 'Tu nombre', { autoComplete: 'name', placeholder: 'Nombre y apellido' })}
                    {field('email', 'E-mail', { type: 'email', autoComplete: 'email', placeholder: 'vos@ejemplo.com' })}
                    {field('password', 'Contraseña', { type: 'password', autoComplete: 'new-password' })}
                    {field('password_confirmation', 'Repetí la contraseña', { type: 'password', autoComplete: 'new-password' })}
                    <Button type="submit" className="mt-2 w-full" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Crear cuenta
                    </Button>
                </div>

                <div className="text-muted-foreground text-center text-sm">
                    ¿Ya tenés cuenta? <TextLink href={route('login')}>Ingresá</TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
