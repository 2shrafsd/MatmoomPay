<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\PaymentIntents\CancelPaymentIntent;
use App\Actions\PaymentIntents\ConfirmPaymentIntent;
use App\Actions\PaymentIntents\CreatePaymentIntent;
use App\Http\Api\ApiResponse;
use App\Http\Requests\Api\ConfirmPaymentIntentRequest;
use App\Http\Requests\Api\ListRequest;
use App\Http\Requests\Api\StorePaymentIntentRequest;
use App\Http\Resources\PaymentIntentResource;
use App\Models\PaymentIntent;
use Illuminate\Http\JsonResponse;

class PaymentIntentController extends ApiController
{
    public function index(ListRequest $request): JsonResponse
    {
        $intents = $this->page(
            PaymentIntent::query()->forAccount($this->account()->merchant, $this->account()->livemode)->with(PaymentIntentResource::RELATIONS),
            $request,
        );

        return ApiResponse::success(ApiResponse::RETRIEVED, PaymentIntentResource::collection($intents)->resolve($request));
    }

    public function store(StorePaymentIntentRequest $request, CreatePaymentIntent $createPaymentIntent): JsonResponse
    {
        $intent = $createPaymentIntent->handle($this->account(), $request->toData());

        return ApiResponse::success(ApiResponse::CREATED, PaymentIntentResource::make($intent)->resolve($request));
    }

    public function show(string $paymentIntentId): JsonResponse
    {
        return $this->respond($this->findOwned(PaymentIntent::class, $paymentIntentId, with: PaymentIntentResource::RELATIONS));
    }

    public function showByReference(string $clientReferenceId): JsonResponse
    {
        return $this->respond($this->findOwned(PaymentIntent::class, $clientReferenceId, 'client_reference_id', PaymentIntentResource::RELATIONS));
    }

    public function confirm(ConfirmPaymentIntentRequest $request, string $paymentIntentId, ConfirmPaymentIntent $confirmPaymentIntent): JsonResponse
    {
        $intent = $confirmPaymentIntent->handle(
            $this->findOwned(PaymentIntent::class, $paymentIntentId, with: ['paymentMethod']),
            $request->paymentMethod(),
        );

        return ApiResponse::success(ApiResponse::UPDATED, PaymentIntentResource::make($intent)->resolve($request));
    }

    public function cancel(string $paymentIntentId, CancelPaymentIntent $cancelPaymentIntent): JsonResponse
    {
        $intent = $cancelPaymentIntent->handle($this->findOwned(PaymentIntent::class, $paymentIntentId));

        return ApiResponse::success(ApiResponse::UPDATED, PaymentIntentResource::make($intent)->resolve());
    }

    private function respond(PaymentIntent $intent): JsonResponse
    {
        return ApiResponse::success(ApiResponse::RETRIEVED, PaymentIntentResource::make($intent)->resolve());
    }
}
