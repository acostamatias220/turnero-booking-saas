<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Booking\AppointmentLifecycle;
use App\Booking\SlotUnavailableException;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a verified provider event. Safe to run more than once for the same
 * event (retries, duplicated deliveries): the event row is claimed atomically.
 */
final class ProcessPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120, 600];

    public function __construct(public readonly int $webhookEventId) {}

    public function handle(TenantContext $tenant, AppointmentLifecycle $lifecycle): void
    {
        $event = WebhookEvent::query()->findOrFail($this->webhookEventId);

        if ($event->processed_at !== null) {
            return;
        }

        /** @var array{checkout_id?: string, payment_id?: string} $data */
        $data = $event->payload['data'] ?? [];

        // Row lock on the payment serialises concurrent deliveries of related events
        // (e.g. a retry racing the original), so status checks below are reliable.
        $result = DB::transaction(function () use ($event, $data, $tenant, $lifecycle): string {
            $payment = Payment::acrossTenants()
                ->with('business')
                ->where('checkout_id', $data['checkout_id'] ?? '')
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                return 'unknown_checkout';
            }

            return $tenant->run($payment->business, fn (): string => match ($event->type) {
                'payment.approved' => $this->approve($payment, (string) ($data['payment_id'] ?? ''), $lifecycle),
                'payment.rejected' => $this->reject($payment, $lifecycle),
                default => 'ignored',
            });
        });

        $this->finish($event, $result);
    }

    private function approve(Payment $payment, string $providerPaymentId, AppointmentLifecycle $lifecycle): string
    {
        if ($payment->status === PaymentStatus::Approved) {
            return 'already_approved';
        }

        $payment->fill([
            'status' => PaymentStatus::Approved,
            'provider_payment_id' => $providerPaymentId !== '' ? $providerPaymentId : null,
            'paid_at' => CarbonImmutable::now(),
        ])->save();

        try {
            $lifecycle->confirmPaid($payment->appointment);

            return 'confirmed';
        } catch (SlotUnavailableException) {
            // The hold expired and somebody else booked the slot meanwhile.
            $payment->status = PaymentStatus::RefundRequired;
            $payment->save();
            Log::warning('Late payment for a slot that was taken; refund required.', ['payment' => $payment->id]);

            return 'refund_required';
        }
    }

    private function reject(Payment $payment, AppointmentLifecycle $lifecycle): string
    {
        if ($payment->status !== PaymentStatus::Pending) {
            return 'stale_rejection'; // an approval already won; never downgrade it
        }

        $payment->status = PaymentStatus::Rejected;
        $payment->save();

        $appointment = $payment->appointment;

        if ($appointment->status === AppointmentStatus::PendingPayment) {
            $lifecycle->cancel($appointment, 'payment_rejected');
        }

        return 'rejected';
    }

    private function finish(WebhookEvent $event, string $result): void
    {
        DB::table('webhook_events')
            ->where('id', $event->id)
            ->whereNull('processed_at')
            ->update(['processed_at' => CarbonImmutable::now(), 'result' => $result, 'updated_at' => CarbonImmutable::now()]);
    }
}
