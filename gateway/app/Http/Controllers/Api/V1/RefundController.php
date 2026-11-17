<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Refunds\CreateRefund;
use App\Http\Api\ApiResponse;
use App\Http\Requests\Api\ListRequest;
use App\Http\Requests\Api\StoreRefundRequest;
use App\Http\Resources\RefundResource;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;

class RefundController extends ApiController
{
    public function index(ListRequest $request): JsonResponse
    {
        $refunds = $this->page(
            Refund::query()->forAccount($this->account()->merchant, $this->account()->livemode)->with(RefundResource::RELATIONS),
            $request,
        );

        return ApiResponse::success(ApiResponse::RETRIEVED, RefundResource::collection($refunds)->resolve($request));
    }

    public function store(StoreRefundRequest $request, CreateRefund $createRefund): JsonResponse
    {
        $validated = $request->validated();

        $refund = $createRefund->handle(
            $request->payment(),
            isset($validated['amount']) ? (int) $validated['amount'] : null,
            $validated['reason'],
            $validated['metadata'] ?? [],
        );

        return ApiResponse::success(ApiResponse::CREATED, RefundResource::make($refund)->resolve($request));
    }

    public function show(string $refundId): JsonResponse
    {
        return ApiResponse::success(
            ApiResponse::RETRIEVED,
            RefundResource::make($this->findOwned(Refund::class, $refundId, with: RefundResource::RELATIONS))->resolve(),
        );
    }
}
