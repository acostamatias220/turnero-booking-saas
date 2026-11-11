<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Booking\SlotBlocks;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Holiday;
use App\Models\Payment;
use App\Models\Service;
use App\Models\TimeOff;
use App\Models\User;
use App\Models\WorkingHour;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Three realistic demo businesses with history (last 30 days) and an agenda for
 * the next two weeks. DEMO DATA ONLY: every user's password is "password".
 *
 * @phpstan-type StaffSpec array{name: string, email: string, title: string, owner?: bool, services: list<string>, hours: array<int, list<array{0: string, 1: string}>>}
 * @phpstan-type ServiceSpec array{name: string, description: string, minutes: int, buffer: int, price: int}
 */
final class DemoBusinessSeeder extends Seeder
{
    private const CUSTOMERS = [
        'Sofía Fernández', 'Mateo González', 'Valentina Rodríguez', 'Benjamín López', 'Martina Díaz', 'Thiago Martínez',
        'Catalina Pérez', 'Joaquín Romero', 'Emilia Sánchez', 'Felipe Álvarez', 'Lucía Torres', 'Tomás Ruiz',
        'Julieta Ramírez', 'Lautaro Flores', 'Camila Benítez', 'Santino Acosta', 'Mía Medina', 'Bautista Herrera',
        'Delfina Suárez', 'Agustín Aguirre', 'Renata Giménez', 'Nicolás Molina', 'Abril Castro', 'Facundo Ortiz',
    ];

    /** National holidays used by the demo (a real install runs `holidays:import`). */
    private const HOLIDAYS = [
        '2026-11-23' => 'Día de la Soberanía Nacional',
        '2026-12-08' => 'Inmaculada Concepción de María',
        '2026-12-25' => 'Navidad',
        '2027-01-01' => 'Año Nuevo',
        '2027-02-15' => 'Carnaval',
        '2027-02-16' => 'Carnaval',
    ];

    public function __construct(private readonly TenantContext $tenant, private readonly SlotBlocks $blocks) {}

    public function run(): void
    {
        mt_srand(2026);

        $this->business(
            ['name' => 'Barbería El Tano', 'slug' => 'barberia-el-tano', 'category' => 'barbershop', 'tagline' => 'Cortes clásicos y barba al vapor desde 1998', 'address' => 'Av. Corrientes 3120, Almagro, CABA', 'phone' => '11 4862-1998', 'accent_color' => '#b45309', 'deposit_percent' => 30, 'slot_interval_minutes' => 15],
            [
                ['name' => 'Corte clásico', 'description' => 'Tijera y máquina, lavado incluido.', 'minutes' => 30, 'buffer' => 10, 'price' => 12_000],
                ['name' => 'Corte + barba', 'description' => 'Corte completo y perfilado de barba con toalla caliente.', 'minutes' => 50, 'buffer' => 10, 'price' => 18_500],
                ['name' => 'Arreglo de barba', 'description' => 'Perfilado a navaja.', 'minutes' => 20, 'buffer' => 5, 'price' => 7_000],
                ['name' => 'Color / mechas', 'description' => 'Consultá antes por el tono.', 'minutes' => 90, 'buffer' => 15, 'price' => 35_000],
            ],
            [
                ['name' => 'Cayetano "Tano" Russo', 'email' => 'tano@turnero.test', 'title' => 'Barbero y dueño', 'owner' => true, 'services' => ['Corte clásico', 'Corte + barba', 'Arreglo de barba'], 'hours' => [2 => [['10:00', '14:00'], ['15:00', '20:00']], 3 => [['10:00', '14:00'], ['15:00', '20:00']], 4 => [['10:00', '14:00'], ['15:00', '20:00']], 5 => [['10:00', '14:00'], ['15:00', '20:00']], 6 => [['09:00', '15:00']]]],
                ['name' => 'Bruno Ledesma', 'email' => 'bruno@turnero.test', 'title' => 'Barbero', 'services' => ['Corte clásico', 'Corte + barba', 'Arreglo de barba', 'Color / mechas'], 'hours' => [1 => [['12:00', '20:00']], 2 => [['12:00', '20:00']], 3 => [['12:00', '20:00']], 5 => [['12:00', '20:00']], 6 => [['09:00', '17:00']]]],
                ['name' => 'Juli Paz', 'email' => 'juli@turnero.test', 'title' => 'Colorista', 'services' => ['Corte clásico', 'Color / mechas'], 'hours' => [3 => [['11:00', '19:00']], 4 => [['11:00', '19:00']], 5 => [['11:00', '19:00']], 6 => [['10:00', '16:00']]]],
            ],
        );

        $this->business(
            ['name' => 'Estudio Pilates Núcleo', 'slug' => 'pilates-nucleo', 'category' => 'fitness', 'tagline' => 'Pilates reformer en grupos reducidos', 'address' => 'Gorriti 4870, Palermo, CABA', 'phone' => '11 3456-7788', 'accent_color' => '#0d9488', 'deposit_percent' => 0, 'slot_interval_minutes' => 60, 'cancellation_notice_hours' => 12],
            [
                ['name' => 'Reformer individual', 'description' => 'Clase personalizada de 55 minutos.', 'minutes' => 55, 'buffer' => 5, 'price' => 16_000],
                ['name' => 'Clase de prueba', 'description' => 'Evaluación postural + primera clase.', 'minutes' => 55, 'buffer' => 5, 'price' => 9_000],
                ['name' => 'Rehabilitación', 'description' => 'Con indicación médica.', 'minutes' => 55, 'buffer' => 5, 'price' => 19_000],
            ],
            [
                ['name' => 'Carla Benedetti', 'email' => 'carla@turnero.test', 'title' => 'Instructora y dueña', 'owner' => true, 'services' => ['Reformer individual', 'Clase de prueba', 'Rehabilitación'], 'hours' => self::weekdays([['07:00', '12:00'], ['17:00', '21:00']])],
                ['name' => 'Ramiro Vidal', 'email' => 'ramiro@turnero.test', 'title' => 'Kinesiólogo', 'services' => ['Rehabilitación', 'Reformer individual'], 'hours' => [1 => [['14:00', '20:00']], 3 => [['14:00', '20:00']], 5 => [['14:00', '20:00']], 6 => [['09:00', '13:00']]]],
            ],
        );

        $this->business(
            ['name' => 'Consultorio Dra. Ruiz', 'slug' => 'dra-ruiz', 'category' => 'health', 'tagline' => 'Dermatología clínica y estética', 'address' => 'Av. Cabildo 2230, piso 4 "B", Belgrano', 'phone' => '11 4781-2230', 'accent_color' => '#2563eb', 'deposit_percent' => 50, 'slot_interval_minutes' => 20, 'min_notice_minutes' => 240, 'cancellation_notice_hours' => 48],
            [
                ['name' => 'Consulta dermatológica', 'description' => 'Primera vez o control.', 'minutes' => 20, 'buffer' => 0, 'price' => 30_000],
                ['name' => 'Control de lunares (dermatoscopía)', 'description' => 'Mapeo completo con dermatoscopio digital.', 'minutes' => 40, 'buffer' => 0, 'price' => 55_000],
                ['name' => 'Peeling químico', 'description' => 'Requiere consulta previa.', 'minutes' => 40, 'buffer' => 20, 'price' => 70_000],
            ],
            [
                ['name' => 'Dra. Laura Ruiz', 'email' => 'ruiz@turnero.test', 'title' => 'Dermatóloga · M.N. 112.233', 'owner' => true, 'services' => ['Consulta dermatológica', 'Control de lunares (dermatoscopía)', 'Peeling químico'], 'hours' => [1 => [['09:00', '13:00']], 2 => [['14:00', '19:00']], 4 => [['09:00', '13:00'], ['14:00', '17:00']]]],
            ],
        );
    }

    /**
     * @param  list<array{0: string, 1: string}>  $ranges
     * @return array<int, list<array{0: string, 1: string}>>
     */
    private static function weekdays(array $ranges): array
    {
        return array_fill_keys([1, 2, 3, 4, 5], $ranges);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<ServiceSpec>  $serviceSpecs
     * @param  list<StaffSpec>  $staffSpecs
     */
    private function business(array $attributes, array $serviceSpecs, array $staffSpecs): void
    {
        $business = Business::query()->create([...['timezone' => 'America/Argentina/Buenos_Aires', 'currency' => 'ARS'], ...$attributes]);

        $this->tenant->run($business, function () use ($business, $serviceSpecs, $staffSpecs): void {
            $services = collect($serviceSpecs)->mapWithKeys(fn (array $s): array => [$s['name'] => Service::query()->create([
                'name' => $s['name'],
                'description' => $s['description'],
                'duration_minutes' => $s['minutes'],
                'buffer_minutes' => $s['buffer'],
                'price_cents' => $s['price'] * 100,
                'is_active' => true,
            ])]);

            $staff = [];
            $last = null;
            foreach ($staffSpecs as $spec) {
                $user = User::query()->create([
                    'business_id' => $business->id,
                    'role' => ($spec['owner'] ?? false) ? Role::Owner : Role::Staff,
                    'name' => $spec['name'],
                    'email' => $spec['email'],
                    'title' => $spec['title'],
                    'password' => 'password',
                    'is_bookable' => true,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->services()->sync($services->only($spec['services'])->pluck('id')->all());

                foreach ($spec['hours'] as $weekday => $ranges) {
                    foreach ($ranges as [$from, $to]) {
                        WorkingHour::query()->create(['user_id' => $user->id, 'weekday' => $weekday, 'starts_at' => $from, 'ends_at' => $to]);
                    }
                }
                $staff[] = $last = $user->load(['services', 'workingHours']);
            }

            foreach (self::HOLIDAYS as $date => $name) {
                Holiday::query()->create(['date' => $date, 'name' => $name]);
            }

            $customers = collect(self::CUSTOMERS)->shuffle()->take(18)->map(fn (string $name): Customer => Customer::query()->create([
                'name' => $name,
                'email' => Str::slug($name, '.').'@example.com',
                'phone' => '11 '.mt_rand(2000, 6999).'-'.mt_rand(1000, 9999),
            ]))->values();

            // A week of vacation for the last professional, so the agenda shows it.
            assert($last instanceof User);
            $vacationStart = $business->now()->addDays(9)->startOfDay();
            TimeOff::query()->create([
                'user_id' => $last->id,
                'starts_at' => $vacationStart->utc(),
                'ends_at' => $vacationStart->addDays(3)->utc(),
                'reason' => 'Vacaciones',
            ]);

            $today = $business->now()->startOfDay();
            for ($offset = -30; $offset <= 14; $offset++) {
                $day = $today->addDays($offset);

                if (array_key_exists($day->toDateString(), self::HOLIDAYS)) {
                    continue;
                }

                foreach ($staff as $member) {
                    if ($offset >= 9 && $offset < 12 && $member->is($last)) {
                        continue;
                    }
                    $this->fillDay($business, $member, $day, array_values($customers->all()), $offset);
                }
            }
        });
    }

    /** @param  list<Customer>  $customers */
    private function fillDay(Business $business, User $member, CarbonImmutable $day, array $customers, int $offset): void
    {
        $occupancy = $offset < 0 ? 0.65 : max(0.15, 0.7 - $offset * 0.04);
        $now = CarbonImmutable::now();

        foreach ($member->workingHours->where('weekday', $day->isoWeekday()) as $range) {
            $cursor = CarbonImmutable::parse("{$day->toDateString()} {$range->starts_at}", $business->timezone);
            $end = CarbonImmutable::parse("{$day->toDateString()} {$range->ends_at}", $business->timezone);

            while ($cursor->lessThan($end)) {
                /** @var Service $service */
                $service = $member->services->random();
                $finish = $cursor->addMinutes($service->duration_minutes);

                if ($finish->greaterThan($end) || mt_rand(1, 100) > $occupancy * 100) {
                    $cursor = $cursor->addMinutes($business->slot_interval_minutes);

                    continue;
                }

                $start = $cursor->utc();
                $status = $this->statusFor($start, $now);
                $deposit = intdiv($service->price_cents * $business->deposit_percent, 100);

                $appointment = Appointment::query()->create([
                    'staff_id' => $member->id,
                    'service_id' => $service->id,
                    'customer_id' => $customers[array_rand($customers)]->id,
                    'status' => $status,
                    'starts_at' => $start,
                    'ends_at' => $start->addMinutes($service->duration_minutes),
                    'blocked_until' => $start->addMinutes($service->duration_minutes + $service->buffer_minutes),
                    'price_cents' => $service->price_cents,
                    'deposit_cents' => $deposit,
                ]);

                if ($status->isActive()) {
                    $this->blocks->reserve($appointment);
                }

                if ($deposit > 0) {
                    Payment::query()->create([
                        'appointment_id' => $appointment->id,
                        'provider' => 'pagosimulado',
                        'checkout_id' => 'cs_'.Str::lower((string) Str::ulid()),
                        'provider_payment_id' => 'pay_'.Str::lower(Str::random(20)),
                        'amount_cents' => $deposit,
                        'currency' => 'ARS',
                        'status' => $status === AppointmentStatus::Cancelled ? PaymentStatus::Refunded : PaymentStatus::Approved,
                        'paid_at' => $appointment->created_at,
                    ]);
                }

                $step = (int) (ceil(($service->duration_minutes + $service->buffer_minutes) / $business->slot_interval_minutes) * $business->slot_interval_minutes);
                $cursor = $cursor->addMinutes($step);
            }
        }
    }

    private function statusFor(CarbonImmutable $start, CarbonImmutable $now): AppointmentStatus
    {
        $roll = mt_rand(1, 100);

        if ($start->greaterThan($now)) {
            return $roll <= 7 ? AppointmentStatus::Cancelled : AppointmentStatus::Confirmed;
        }

        return match (true) {
            $roll <= 8 => AppointmentStatus::NoShow,
            $roll <= 18 => AppointmentStatus::Cancelled,
            default => AppointmentStatus::Completed,
        };
    }
}
