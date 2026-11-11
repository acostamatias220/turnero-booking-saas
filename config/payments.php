<?php

declare(strict_types=1);

return [
    /*
    | Payment gateway used for booking deposits. Only the PagoSimulado sandbox ships
    | with the project; a Mercado Pago / Stripe adapter would implement the same
    | App\Payments\PaymentGateway contract.
    */
    'driver' => env('PAYMENTS_DRIVER', 'pagosimulado'),

    // Shared secret used to sign webhooks (HMAC-SHA256). Never commit a real value.
    'webhook_secret' => env('PAYMENTS_WEBHOOK_SECRET'),

    // Max age (seconds) of a signed webhook, to stop replays of captured requests.
    'webhook_tolerance' => (int) env('PAYMENTS_WEBHOOK_TOLERANCE', 300),
];
