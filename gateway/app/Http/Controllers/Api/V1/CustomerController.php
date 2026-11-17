<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Customers\CreateCustomer;
use App\Enums\SubscriptionStatus;
use App\Exceptions\GatewayException;
use App\Http\Api\ApiResponse;
use App\Http\Requests\Api\ListRequest;
use App\Http\Requests\Api\StoreCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

class CustomerController extends ApiController
{
    public function index(ListRequest $request): JsonResponse
    {
        $customers = $this->page(Customer::query()->forAccount($this->account()->merchant, $this->account()->livemode), $request);

        return ApiResponse::success(ApiResponse::RETRIEVED, CustomerResource::collection($customers)->resolve($request));
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $createCustomer): JsonResponse
    {
        $customer = $createCustomer->handle($this->account(), (string) $request->validated('client_customer_id'));

        return ApiResponse::success(ApiResponse::CREATED, CustomerResource::make($customer)->resolve($request));
    }

    public function show(string $customerId): JsonResponse
    {
        return ApiResponse::success(ApiResponse::RETRIEVED, CustomerResource::make($this->findOwned(Customer::class, $customerId))->resolve());
    }

    public function destroy(string $customerId): JsonResponse
    {
        $customer = $this->findOwned(Customer::class, $customerId);

        // Deleting removes the customer's details and saved cards; payments, subscriptions and invoices stay.
        if ($customer->subscriptions()->where('status', '!=', SubscriptionStatus::Cancelled)->exists()) {
            throw GatewayException::cannotUpdate('This customer has an active subscription. Cancel it before deleting the customer.');
        }

        $customer->delete();

        return ApiResponse::success(ApiResponse::REMOVED);
    }
}
