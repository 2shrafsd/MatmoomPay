<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Subscriptions\CancelSubscription;
use App\Http\Api\ApiResponse;
use App\Http\Requests\Api\ListRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends ApiController
{
    public function index(ListRequest $request): JsonResponse
    {
        $subscriptions = $this->page(
            Subscription::query()->forAccount($this->account()->merchant, $this->account()->livemode)->with(SubscriptionResource::RELATIONS),
            $request,
        );

        return ApiResponse::success(ApiResponse::RETRIEVED, SubscriptionResource::collection($subscriptions)->resolve($request));
    }

    public function show(string $subscriptionId): JsonResponse
    {
        return ApiResponse::success(
            ApiResponse::RETRIEVED,
            SubscriptionResource::make($this->findOwned(Subscription::class, $subscriptionId, with: SubscriptionResource::RELATIONS))->resolve(),
        );
    }

    public function invoices(ListRequest $request, string $subscriptionId): JsonResponse
    {
        $subscription = $this->findOwned(Subscription::class, $subscriptionId);
        $invoices = $subscription->invoices()->with(InvoiceResource::RELATIONS)->latest('id')->skip($request->skip())->take($request->limit())->get();

        return ApiResponse::success(ApiResponse::RETRIEVED, InvoiceResource::collection($invoices)->resolve($request));
    }

    public function cancel(Request $request, string $subscriptionId, CancelSubscription $cancelSubscription): JsonResponse
    {
        $validated = $request->validate(['at_period_end' => ['sometimes', 'boolean']]);

        $subscription = $cancelSubscription->handle(
            $this->findOwned(Subscription::class, $subscriptionId),
            (bool) ($validated['at_period_end'] ?? false),
        );

        return ApiResponse::success(ApiResponse::UPDATED, SubscriptionResource::make($subscription->load(SubscriptionResource::RELATIONS))->resolve());
    }
}
