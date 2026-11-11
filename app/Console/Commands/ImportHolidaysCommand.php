<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Holidays\HolidayProvider;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ImportHolidaysCommand extends Command
{
    protected $description = 'Import Argentine national holidays as closed days';

    protected $signature = 'holidays:import {year? : Defaults to the current year} {--business= : Only this business slug}';

    public function handle(HolidayProvider $provider): int
    {
        $year = (int) ($this->argument('year') ?? CarbonImmutable::now()->year);

        try {
            $holidays = $provider->forYear($year);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $businesses = Business::query()
            ->when($this->option('business'), fn ($q, $slug) => $q->where('slug', $slug))
            ->pluck('id');

        $now = CarbonImmutable::now();
        $rows = $businesses->crossJoin($holidays)->map(fn (array $pair): array => [
            'business_id' => $pair[0],
            'date' => $pair[1]['date'],
            'name' => $pair[1]['name'],
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        // Idempotent: re-running refreshes names instead of duplicating rows.
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('holidays')->upsert($chunk, ['business_id', 'date'], ['name', 'updated_at']);
        }

        $this->components->info(sprintf('Imported %d holidays for %d business(es).', count($holidays), $businesses->count()));

        return self::SUCCESS;
    }
}
