<?php

declare(strict_types=1);

use App\Payments\WebhookSignature;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->signature = new WebhookSignature('s3cr3t', toleranceSeconds: 300);
    $this->now = CarbonImmutable::parse('2026-11-11 15:00:00', 'UTC');
    $this->body = '{"id":"evt_1","type":"payment.approved"}';
});

it('accepts a payload it signed', function () {
    $header = $this->signature->sign($this->body, $this->now->getTimestamp());

    expect($this->signature->verify($this->body, $header, $this->now))->toBeTrue();
});

it('rejects a tampered body', function () {
    $header = $this->signature->sign($this->body, $this->now->getTimestamp());

    expect($this->signature->verify(str_replace('approved', 'rejected', $this->body), $header, $this->now))->toBeFalse();
});

it('rejects replays outside the tolerance window', function () {
    $header = $this->signature->sign($this->body, $this->now->subMinutes(6)->getTimestamp());

    expect($this->signature->verify($this->body, $header, $this->now))->toBeFalse();
});

it('rejects a re-dated header because the timestamp is signed too', function () {
    $old = $this->signature->sign($this->body, $this->now->subHour()->getTimestamp());
    $forged = preg_replace('/t=\d+/', 't='.$this->now->getTimestamp(), $old);

    expect($this->signature->verify($this->body, $forged, $this->now))->toBeFalse();
});

it('rejects signatures made with another secret', function () {
    $header = (new WebhookSignature('other'))->sign($this->body, $this->now->getTimestamp());

    expect($this->signature->verify($this->body, $header, $this->now))->toBeFalse();
});

it('accepts any of several v1 values to allow secret rotation', function () {
    $valid = $this->signature->sign($this->body, $this->now->getTimestamp());
    $header = 't='.$this->now->getTimestamp().',v1=deadbeef,'.explode(',', $valid)[1];

    expect($this->signature->verify($this->body, $header, $this->now))->toBeTrue();
});

it('rejects malformed headers', function (?string $header) {
    expect($this->signature->verify($this->body, $header, $this->now))->toBeFalse();
})->with([null, '', 'garbage', 't=abc,v1=123', 'v1=123']);

it('refuses to run without a secret', function () {
    new WebhookSignature('');
})->throws(RuntimeException::class);
