<?php

use App\Support\Money;

test('sudanese pounds are whole amounts without decimals', function () {
    expect(Money::format(18_500))->toBe('SDG 18,500')
        ->and(Money::decimal(4_825_075))->toBe('4,825,075')
        ->and(Money::example())->toBe('18,500');
});

test('typed amounts accept thousands separators but never fractions', function (string $typed, ?int $expected) {
    if ($expected === null) {
        expect(fn () => Money::toMinorUnits($typed))->toThrow(InvalidArgumentException::class);

        return;
    }

    expect(Money::toMinorUnits($typed))->toBe($expected);
})->with([
    'plain' => ['18500', 18_500],
    'with separators' => ['1,250,000', 1_250_000],
    'fraction' => ['18.50', null],
    'misplaced separator' => ['18,50', null],
    'letters' => ['18k', null],
]);

test('payments below the minimum are refused and small payments still carry the minimum fee', function () {
    [, $headers] = apiKeyFor();

    $this->postJson('/api/v1/checkout/session', checkoutPayload(['products' => [['name' => 'Gum', 'quantity' => 1, 'unit_amount' => 99]]]), $headers)
        ->assertStatus(400)
        ->assertJsonPath('data.error.0.message', 'The amount must be at least SDG 100.');

    expect(Money::fee(100, 250, 0))->toBe(3)
        ->and(Money::fee(20, 250, 0))->toBe(1)
        ->and(Money::fee(5_000, 0, 0))->toBe(0);
});
