<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus as S;

it('models the appointment lifecycle as a state machine', function (S $from, S $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    [S::PendingPayment, S::Confirmed, true],
    [S::PendingPayment, S::Expired, true],
    [S::PendingPayment, S::Completed, false],
    [S::Confirmed, S::Completed, true],
    [S::Confirmed, S::NoShow, true],
    [S::Confirmed, S::Cancelled, true],
    [S::Expired, S::Confirmed, true],
    [S::Cancelled, S::Confirmed, false],
    [S::Completed, S::Cancelled, false],
    [S::NoShow, S::Completed, false],
]);

it('only pending and confirmed turnos occupy time', function () {
    expect(array_map(fn (S $s) => $s->value, array_filter(S::cases(), fn (S $s) => $s->isActive())))
        ->toEqualCanonicalizing(S::activeValues());
});

it('has a Spanish label for every status', function () {
    foreach (S::cases() as $status) {
        expect($status->label())->not->toBeEmpty();
    }
});
