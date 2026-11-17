<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Exceptions\GatewayException;
use App\Models\Customer;
use App\Support\GatewayAccount;
use Illuminate\Database\UniqueConstraintViolationException;

final class CreateCustomer
{
    public function handle(GatewayAccount $account, string $clientCustomerId): Customer
    {
        $exists = Customer::query()
            ->forAccount($account->merchant, $account->livemode)
            ->where('client_customer_id', $clientCustomerId)
            ->exists();

        if ($exists) {
            throw GatewayException::alreadyExists('A customer with this client_customer_id already exists.');
        }

        try {
            return Customer::create([
                'merchant_id' => $account->merchant->id,
                'livemode' => $account->livemode,
                'client_customer_id' => $clientCustomerId,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw GatewayException::alreadyExists('A customer with this client_customer_id already exists.');
        }
    }
}
