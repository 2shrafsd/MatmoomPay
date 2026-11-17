<?php

use App\Models\ApiKey;
use App\Models\Merchant;
use Database\Factories\ApiKeyFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Creates a secret API key and returns the headers that authenticate with it.
 *
 * @return array{0: ApiKey, 1: array<string, string>}
 */
function apiKeyFor(?Merchant $merchant = null, bool $livemode = false): array
{
    $key = ApiKey::factory()
        ->for($merchant ?? Merchant::factory())
        ->state(['livemode' => $livemode])
        ->create();

    return [$key, ['matmoom-api-key' => (string) ApiKeyFactory::$lastToken, 'Accept' => 'application/json']];
}

/**
 * @return array<string, mixed>
 */
function checkoutPayload(array $overrides = []): array
{
    return array_replace([
        'client_reference_id' => 'order-1001',
        'mode' => 'payment',
        'products' => [
            ['name' => 'Frankincense, 250 g', 'quantity' => 2, 'unit_amount' => 3_500],
            ['name' => 'Gift wrapping', 'quantity' => 1, 'unit_amount' => 500],
        ],
        'success_url' => 'https://shop.example/success',
        'cancel_url' => 'https://shop.example/cancel',
        'metadata' => ['Customer name' => 'Aisha Al Balushi', 'order id' => 1001],
    ], $overrides);
}
