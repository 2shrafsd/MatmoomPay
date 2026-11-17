<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Subscriptions\CreatePlan;
use App\Enums\PlanInterval;
use App\Http\Api\ApiResponse;
use App\Http\Requests\Api\ListRequest;
use App\Http\Requests\Api\StorePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends ApiController
{
    public function index(ListRequest $request): JsonResponse
    {
        $plans = $this->page(Plan::query()->forAccount($this->account()->merchant, $this->account()->livemode), $request);

        return ApiResponse::success(ApiResponse::RETRIEVED, PlanResource::collection($plans)->resolve($request));
    }

    public function store(StorePlanRequest $request, CreatePlan $createPlan): JsonResponse
    {
        $validated = $request->validated();

        $plan = $createPlan->handle(
            $this->account(),
            $validated['name'],
            (int) $validated['amount'],
            PlanInterval::from($validated['interval']),
            (int) ($validated['interval_count'] ?? 1),
        );

        return ApiResponse::success(ApiResponse::CREATED, PlanResource::make($plan)->resolve($request));
    }

    public function show(string $planId): JsonResponse
    {
        return ApiResponse::success(ApiResponse::RETRIEVED, PlanResource::make($this->findOwned(Plan::class, $planId))->resolve());
    }
}
