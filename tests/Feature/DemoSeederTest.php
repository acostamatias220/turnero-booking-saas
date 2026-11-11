<?php

declare(strict_types=1);

use App\Models\Appointment;
use App\Models\AppointmentBlock;
use App\Models\Business;
use Database\Seeders\DatabaseSeeder;

it('seeds three demo businesses with a consistent, overlap-free agenda', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Business::query()->pluck('slug')->all())
        ->toEqualCanonicalizing(['barberia-el-tano', 'pilates-nucleo', 'dra-ruiz']);

    $active = Appointment::acrossTenants()->whereIn('status', ['confirmed', 'pending_payment'])->get();
    expect($active)->not->toBeEmpty();

    // Every active appointment owns exactly the 5-minute blocks it spans (incl. buffer).
    $expectedBlocks = $active->sum(fn (Appointment $a): int => (int) ceil($a->starts_at->diffInMinutes($a->blocked_until) / 5));
    expect(AppointmentBlock::query()->count())->toBe($expectedBlocks);

    // No professional has two active appointments overlapping in time.
    $active->groupBy('staff_id')->each(function ($list): void {
        $sorted = $list->sortBy('starts_at')->values();
        for ($i = 1; $i < $sorted->count(); $i++) {
            expect($sorted[$i]->starts_at->greaterThanOrEqualTo($sorted[$i - 1]->blocked_until))->toBeTrue();
        }
    });
});
