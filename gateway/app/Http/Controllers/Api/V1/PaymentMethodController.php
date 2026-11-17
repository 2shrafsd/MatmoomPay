<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\GatewayException;
use App\Http\Api\ApiResponse;
use App\Http\Requests\Api\ListRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\Customer;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends ApiController
{
    public function index(ListRequest $request): JsonResponse
    {
        $customerId = $request->validated('customer_id') ?? throw GatewayException::invalid('customer_id', 'The customer_id query parameter is required.');
        $customer = $this->findOwned(Customer::class, (string) $customerId);

        return ApiResponse::success(
            ApiResponse::RETRIEVED,
            PaymentMethodResource::collection($customer->paymentMethods()->latest('id')->get())->resolve($request),
        );
    }

    public function destroy(string $cardId): JsonResponse
    {
        $this->findOwned(PaymentMethod::class, $cardId)->delete();

        return ApiResponse::success(ApiResponse::REMOVED);
    }
}
