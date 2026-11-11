<?php

declare(strict_types=1);

use App\Support\Money;

it('formats pesos the Argentine way', function (int $cents, string $expected) {
    expect(str_replace("\u{a0}", ' ', Money::format($cents)))->toBe($expected);
})->with([
    [1_500_000, '$ 15.000'],
    [1_250_050, '$ 12.500,50'],
    [0, '$ 0'],
]);
