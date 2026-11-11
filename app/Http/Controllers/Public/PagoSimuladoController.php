<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\DeliverSimulatedWebhook;
use App\Models\Appointment;
use App\Models\Payment;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The sandbox provider's hosted checkout. In production this page lives on the
 * provider's domain; here it only knows the checkout id, like a real provider would.
 */
final class PagoSimuladoController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function show(string $checkoutId): Response
    {
        $payment = $this->find($checkoutId);

        return $this->tenant->run($payment->business, function () use ($payment): Response {
            $appointment = Appointment::query()->with(['business', 'service'])->findOrFail($payment->appointment_id);

            return Inertia::render('pagosimulado/checkout', [
                'checkout' => [
                    'id' => $payment->checkout_id,
                    'status' => $payment->status->value,
                    'amount' => Money::format($payment->amount_cents, $payment->currency),
                    'merchant' => $appointment->business->name,
                    'concept' => "Seña · {$appointment->service->name}",
                    'expires_at' => $appointment->hold_expires_at?->toIso8601ZuluString(),
                ],
            ]);
        });
    }

    public function complete(Request $request, string $checkoutId): RedirectResponse
    {
        $validated = $request->validate(['outcome' => ['required', 'in:approved,rejected']]);
        $payment = $this->find($checkoutId);
        $appointment = $this->tenant->run($payment->business, fn (): Appointment => Appointment::query()->findOrFail($payment->appointment_id));

        if ($payment->status === PaymentStatus::Pending) {
            DeliverSimulatedWebhook::dispatch([
                'id' => 'evt_'.Str::lower((string) Str::ulid()),
                'type' => 'payment.'.$validated['outcome'],
                'created' => now()->getTimestamp(),
                'data' => [
                    'checkout_id' => $payment->checkout_id,
                    'payment_id' => 'pay_'.Str::lower(Str::random(20)),
                    'amount' => $payment->amount_cents,
                    'currency' => $payment->currency,
                ],
            ]);
        }

        return redirect()->route('public.appointments.show', [$payment->business->slug, $appointment->public_id]);
    }

    private function find(string $checkoutId): Payment
    {
        return Payment::acrossTenants()->with('business')->where('checkout_id', $checkoutId)->firstOrFail();
    }
}
