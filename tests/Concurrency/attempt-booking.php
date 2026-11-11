<?php

declare(strict_types=1);

/*
| Worker used by DoubleBookingTest: a separate PHP process (own DB connection,
| own transaction) that tries to book a slot at a synchronised instant.
|
| Usage: php attempt-booking.php <businessId> <serviceId> <startsAtIso> <email> <goAtMicrotime> [staffId]
| Prints one line of JSON: {"result":"booked|conflict|error","staff_id":…}
*/

use App\Booking\BookingRequest;
use App\Booking\BookingService;
use App\Booking\SlotUnavailableException;
use App\Models\Business;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $businessId, $serviceId, $startsAt, $email, $goAt] = $argv;
$staffId = isset($argv[6]) ? (int) $argv[6] : null;

$business = Business::query()->findOrFail((int) $businessId);
$app->make(TenantContext::class)->set($business);
$request = new BookingRequest((int) $serviceId, CarbonImmutable::parse($startsAt), $staffId, 'Cliente '.$email, $email);
$service = $app->make(BookingService::class);

// Barrier: everybody is booted and waiting; fire at the same microsecond.
while (microtime(true) < (float) $goAt) {
    usleep(200);
}

try {
    $appointment = $service->book($business, $request);
    echo json_encode(['result' => 'booked', 'staff_id' => $appointment->staff_id]), PHP_EOL;
} catch (SlotUnavailableException) {
    echo json_encode(['result' => 'conflict']), PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['result' => 'error', 'message' => $e::class.': '.$e->getMessage()]), PHP_EOL;
}
