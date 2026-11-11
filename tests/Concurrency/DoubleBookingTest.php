<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentBlock;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Process\Process;

/*
| Real concurrency, not a simulation: N separate PHP processes, each with its own
| MySQL connection, try to book the same slot at the same instant. Data must be
| committed for the other processes to see it, hence DatabaseTruncation instead
| of the transaction-wrapped RefreshDatabase used elsewhere.
*/

/**
 * @return list<array{result: string, staff_id?: int, message?: string}>
 */
function raceForSlot(int $businessId, int $serviceId, string $startsAt, int $workers, ?int $staffId = null): array
{
    $goAt = microtime(true) + 2.5; // time for every worker to boot Laravel

    $processes = [];

    for ($i = 0; $i < $workers; $i++) {
        $args = [PHP_BINARY, base_path('tests/Concurrency/attempt-booking.php'), (string) $businessId, (string) $serviceId, $startsAt, "racer{$i}@example.com", (string) $goAt];

        if ($staffId !== null) {
            $args[] = (string) $staffId;
        }

        $process = new Process($args, base_path(), ['MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync']);
        $process->setTimeout(30);
        $process->start();
        $processes[] = $process;
    }

    return array_map(function (Process $p): array {
        $p->wait();
        $lines = array_values(array_filter(explode(PHP_EOL, trim($p->getOutput()))));

        return json_decode((string) end($lines), true) ?? ['result' => 'error', 'message' => $p->getErrorOutput().$p->getOutput()];
    }, $processes);
}

beforeEach(function () {
    Mail::fake();
    ['business' => $this->business, 'owner' => $this->owner, 'service' => $this->service] = bookableBusiness(['min_notice_minutes' => 0]);
    $this->start = nextMondayAt('10:00');
});

it('lets exactly one of 8 simultaneous requests book the same professional', function () {
    $results = raceForSlot($this->business->id, $this->service->id, $this->start->toIso8601String(), 8, $this->owner->id);

    $counts = collect($results)->countBy('result');

    expect($counts->get('error', 0))->toBe(0, json_encode($results))
        ->and($counts->get('booked'))->toBe(1)
        ->and($counts->get('conflict'))->toBe(7);

    $this->actingAsTenant($this->business);
    expect(Appointment::query()->where('status', AppointmentStatus::Confirmed->value)->count())->toBe(1)
        ->and(AppointmentBlock::query()->count())->toBe(6);
});

it('with "any professional", 8 racers fill exactly the 2 professionals available', function () {
    $bruno = addProfessional($this->business, $this->service);

    $results = raceForSlot($this->business->id, $this->service->id, $this->start->toIso8601String(), 8);

    $booked = collect($results)->where('result', 'booked');

    expect(collect($results)->where('result', 'error'))->toHaveCount(0)
        ->and($booked)->toHaveCount(2)
        ->and($booked->pluck('staff_id')->sort()->values()->all())->toBe(collect([$this->owner->id, $bruno->id])->sort()->values()->all());
});
