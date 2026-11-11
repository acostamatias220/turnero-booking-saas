<?php

declare(strict_types=1);

namespace App\Payments;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Stripe-style signature header: `t=<unix timestamp>,v1=<hex HMAC-SHA256>`.
 *
 * The timestamp is part of the signed payload, so an attacker can neither replay
 * an old captured request (tolerance window) nor re-date it (breaks the HMAC).
 */
final readonly class WebhookSignature
{
    public const HEADER = 'PagoSimulado-Signature';

    public function __construct(
        private string $secret,
        private int $toleranceSeconds = 300,
    ) {
        if ($secret === '') {
            throw new RuntimeException('PAYMENTS_WEBHOOK_SECRET is not configured.');
        }
    }

    public function sign(string $payload, ?int $timestamp = null): string
    {
        $timestamp ??= CarbonImmutable::now()->getTimestamp();

        return sprintf('t=%d,v1=%s', $timestamp, $this->hmac($timestamp, $payload));
    }

    public function verify(string $payload, ?string $header, ?CarbonImmutable $now = null): bool
    {
        if ($header === null || $header === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', $header) as $pair) {
            [$key, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $parts[$key][] = $value;
        }

        $timestamp = isset($parts['t'][0]) && ctype_digit($parts['t'][0]) ? (int) $parts['t'][0] : null;

        if ($timestamp === null || empty($parts['v1'])) {
            return false;
        }

        $now ??= CarbonImmutable::now();

        if (abs($now->getTimestamp() - $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        $expected = $this->hmac($timestamp, $payload);

        // Several v1 values are allowed so the secret can be rotated without downtime.
        foreach ($parts['v1'] as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function hmac(int $timestamp, string $payload): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $this->secret);
    }
}
