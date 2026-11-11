<?php

declare(strict_types=1);

namespace App\Providers;

use App\Holidays\ArgentinaDatosHolidayProvider;
use App\Holidays\HolidayProvider;
use App\Listeners\SendAppointmentNotifications;
use App\Models\Business;
use App\Models\User;
use App\Payments\PagoSimulado\PagoSimuladoGateway;
use App\Payments\PaymentGateway;
use App\Payments\WebhookSignature;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn (): PaymentGateway => match (config('payments.driver')) {
            'pagosimulado' => new PagoSimuladoGateway,
            default => throw new InvalidArgumentException('Unknown payments driver ['.config('payments.driver').'].'),
        });

        $this->app->bind(HolidayProvider::class, fn (): HolidayProvider => new ArgentinaDatosHolidayProvider(
            (string) config('services.holidays.url'),
        ));

        $this->app->bind(WebhookSignature::class, fn (): WebhookSignature => new WebhookSignature(
            (string) config('payments.webhook_secret'),
            (int) config('payments.webhook_tolerance'),
        ));
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        // Lazy loading, silently discarded attributes and missing attributes throw in dev/tests.
        Model::shouldBeStrict(! $this->app->isProduction());

        Event::subscribe(SendAppointmentNotifications::class);

        Gate::define('manage-business', fn (User $user): bool => $user->isOwner() && $user->business_id !== null);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('api-token', fn (Request $request) => [
            Limit::perMinute(5)->by('token-ip:'.$request->ip()),
            Limit::perMinute(5)->by('token-email:'.mb_strtolower((string) $request->input('email'))),
        ]);

        RateLimiter::for('availability', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Booking endpoints create rows and send e-mails: limit per IP *and* per
        // customer e-mail per business, so rotating IPs does not help spammers.
        RateLimiter::for('bookings', function (Request $request) {
            $business = $request->route('business');
            $slug = $business instanceof Business ? $business->slug : (string) $business;

            return [
                Limit::perMinute(10)->by('booking-ip:'.$request->ip()),
                Limit::perHour(5)->by('booking-email:'.$slug.':'.mb_strtolower((string) $request->input('email'))),
            ];
        });

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
    }
}
