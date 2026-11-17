<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Api\ApiResponse;
use App\Http\Requests\Api\ListRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class PaymentController extends ApiController
{
    public function index(ListRequest $request): JsonResponse
    {
        $query = Payment::query()
            ->forAccount($this->account()->merchant, $this->account()->livemode)
            ->with(PaymentResource::RELATIONS)
            ->when($request->validated('checkout_invoice'), fn (Builder $query, string $invoice) => $query->whereHas('checkoutSession', fn (Builder $session) => $session->where('invoice', $invoice)))
            ->when($request->validated('payment_intent'), fn (Builder $query, string $intent) => $query->whereHas('paymentIntent', fn (Builder $pi) => $pi->where('public_id', $intent)));

        return ApiResponse::success(ApiResponse::RETRIEVED, PaymentResource::collection($this->page($query, $request))->resolve($request));
    }

    public function show(string $paymentId): JsonResponse
    {
        return ApiResponse::success(
            ApiResponse::RETRIEVED,
            PaymentResource::make($this->findOwned(Payment::class, $paymentId, with: PaymentResource::RELATIONS))->resolve(),
        );
    }
}
