<?php

declare(strict_types=1);

use App\Booking\BookingCheckout;
use App\Booking\BookingRequest;
use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Jobs\DeliverSimulatedWebhook;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Notifications\AppointmentConfirmedNotification;
use App\Payments\WebhookSignature;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Notification::fake();
    $this->travelTo(CarbonImmutable::parse('2026-11-11 10:00', 'America/Argentina/Buenos_Aires'));
    ['business' => $this->business, 'owner' => $this->owner, 'service' => $this->service] = bookableBusiness(
        ['deposit_percent' => 30, 'hold_minutes' => 15],
        ['price_cents' => 2_000_000],
    );

    $this->actingAsTenant($this->business);
    ['appointment' => $this->appointment] = app(BookingCheckout::class)->handle(
        $this->business,
        new BookingRequest($this->service->id, nextMondayAt('10:00'), null, 'Juana', 'juana@example.com'),
    );
    $this->payment = Payment::query()->firstOrFail();
    $this->forgetTenant(); // webhooks arrive without any tenant context
});

/**
 * @param  array<string, mixed>  $overrides
 */
function sendWebhook(string $type, string $checkoutId, array $overrides = [], ?string $signature = null, string $eventId = 'evt_1')
{
    $body = json_encode(array_replace_recursive([
        'id' => $eventId,
        'type' => $type,
        'data' => ['checkout_id' => $checkoutId, 'payment_id' => 'pay_123', 'amount' => 600000, 'currency' => 'ARS'],
    ], $overrides), JSON_THROW_ON_ERROR);

    $signature ??= app(WebhookSignature::class)->sign($body);

    return test()->call('POST', '/api/webhooks/pagosimulado', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_PAGOSIMULADO_SIGNATURE' => $signature,
    ], $body);
}

it('creates a pending payment for the deposit when booking', function () {
    expect($this->appointment->status)->toBe(AppointmentStatus::PendingPayment)
        ->and($this->payment->amount_cents)->toBe(600_000)
        ->and($this->payment->status)->toBe(PaymentStatus::Pending)
        ->and($this->payment->business_id)->toBe($this->business->id);
});

it('confirms the turno when an approved payment webhook arrives', function () {
    sendWebhook('payment.approved', $this->payment->checkout_id)->assertStatus(202);

    $appointment = Appointment::acrossTenants()->findOrFail($this->appointment->id);

    expect($appointment->status)->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->hold_expires_at)->toBeNull()
        ->and($this->payment->fresh()?->status)->toBe(PaymentStatus::Approved)
        ->and($this->payment->fresh()?->provider_payment_id)->toBe('pay_123')
        ->and(WebhookEvent::query()->first()?->result)->toBe('confirmed');

    Notification::assertSentTo($appointment->customer()->withoutGlobalScopes()->first(), AppointmentConfirmedNotification::class);
});

it('rejects requests with a bad signature', function () {
    sendWebhook('payment.approved', $this->payment->checkout_id, signature: 't='.time().',v1=forged')->assertUnauthorized();

    expect(WebhookEvent::query()->count())->toBe(0)
        ->and(Appointment::acrossTenants()->find($this->appointment->id)?->status)->toBe(AppointmentStatus::PendingPayment);
});

it('rejects replayed (old) signed requests', function () {
    $body = '{"id":"evt_old","type":"payment.approved","data":{"checkout_id":"x"}}';
    $old = app(WebhookSignature::class)->sign($body, now()->subMinutes(10)->getTimestamp());

    $this->call('POST', '/api/webhooks/pagosimulado', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAGOSIMULADO_SIGNATURE' => $old], $body)
        ->assertUnauthorized();
});

it('is idempotent: the same event delivered twice is processed once', function () {
    sendWebhook('payment.approved', $this->payment->checkout_id)->assertStatus(202);
    sendWebhook('payment.approved', $this->payment->checkout_id)->assertOk()->assertJson(['status' => 'duplicate']);

    expect(WebhookEvent::query()->count())->toBe(1);
    Notification::assertSentTimes(AppointmentConfirmedNotification::class, 1);
});

it('a second, different approval event for the same checkout is a no-op', function () {
    sendWebhook('payment.approved', $this->payment->checkout_id, eventId: 'evt_1');
    sendWebhook('payment.approved', $this->payment->checkout_id, eventId: 'evt_2')->assertStatus(202);

    expect(WebhookEvent::query()->where('event_id', 'evt_2')->value('result'))->toBe('already_approved');
    Notification::assertSentTimes(AppointmentConfirmedNotification::class, 1);
});

it('cancels the hold and frees the slot when the payment is rejected', function () {
    sendWebhook('payment.rejected', $this->payment->checkout_id)->assertStatus(202);

    $appointment = Appointment::acrossTenants()->findOrFail($this->appointment->id);

    expect($appointment->status)->toBe(AppointmentStatus::Cancelled)
        ->and($appointment->cancellation_reason)->toBe('payment_rejected')
        ->and(App\Models\AppointmentBlock::query()->where('appointment_id', $appointment->id)->count())->toBe(0);
});

it('never downgrades an approved payment because of a late rejection', function () {
    sendWebhook('payment.approved', $this->payment->checkout_id, eventId: 'evt_ok');
    sendWebhook('payment.rejected', $this->payment->checkout_id, eventId: 'evt_late');

    expect($this->payment->fresh()?->status)->toBe(PaymentStatus::Approved)
        ->and(WebhookEvent::query()->where('event_id', 'evt_late')->value('result'))->toBe('stale_rejection');
});

it('re-reserves the slot when the payment arrives after the hold expired but the slot is still free', function () {
    $this->travel(20)->minutes();
    $this->artisan('appointments:expire-holds')->assertSuccessful();
    expect(Appointment::acrossTenants()->find($this->appointment->id)?->status)->toBe(AppointmentStatus::Expired);

    sendWebhook('payment.approved', $this->payment->checkout_id);

    expect(Appointment::acrossTenants()->find($this->appointment->id)?->status)->toBe(AppointmentStatus::Confirmed);
});

it('flags a refund when the late payment finds its slot already taken', function () {
    $this->travel(20)->minutes();
    $this->artisan('appointments:expire-holds');

    $this->actingAsTenant($this->business);
    app(App\Booking\BookingService::class)->book($this->business, new BookingRequest($this->service->id, nextMondayAt('10:00'), null, 'Otra', 'otra@example.com'));
    $this->forgetTenant();

    sendWebhook('payment.approved', $this->payment->checkout_id);

    expect($this->payment->fresh()?->status)->toBe(PaymentStatus::RefundRequired)
        ->and(Appointment::acrossTenants()->find($this->appointment->id)?->status)->toBe(AppointmentStatus::Expired)
        ->and(WebhookEvent::query()->value('result'))->toBe('refund_required');
});

it('records unknown checkouts without failing', function () {
    sendWebhook('payment.approved', 'cs_does_not_exist')->assertStatus(202);

    expect(WebhookEvent::query()->value('result'))->toBe('unknown_checkout');
});

it('validates the payload shape', function () {
    $body = '{"hello":"world"}';

    $this->call('POST', '/api/webhooks/pagosimulado', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_PAGOSIMULADO_SIGNATURE' => app(WebhookSignature::class)->sign($body),
    ], $body)->assertUnprocessable();
});

describe('PagoSimulado hosted checkout', function () {
    it('shows the amount to pay', function () {
        $this->get(route('pagosimulado.checkout', $this->payment->checkout_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('pagosimulado/checkout')->where('checkout.id', $this->payment->checkout_id));
    });

    it('queues a signed webhook delivery and sends the customer back to the store', function () {
        Queue::fake();

        $this->post(route('pagosimulado.complete', $this->payment->checkout_id), ['outcome' => 'approved'])
            ->assertRedirect(route('public.appointments.show', [$this->business->slug, $this->appointment->public_id]));

        Queue::assertPushed(DeliverSimulatedWebhook::class, fn ($job) => $job->event['type'] === 'payment.approved'
            && $job->event['data']['checkout_id'] === $this->payment->checkout_id);
    });

    it('delivers the webhook over HTTP with a valid signature', function () {
        Http::fake(['*' => Http::response(['status' => 'accepted'], 202)]);

        (new DeliverSimulatedWebhook(['id' => 'evt_x', 'type' => 'payment.approved', 'data' => ['checkout_id' => 'cs_1']]))
            ->handle(app(WebhookSignature::class));

        Http::assertSent(fn ($request) => $request->url() === route('webhooks.pagosimulado')
            && app(WebhookSignature::class)->verify($request->body(), $request->header(WebhookSignature::HEADER)[0] ?? null));
    });
});
