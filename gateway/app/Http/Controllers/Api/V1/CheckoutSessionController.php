<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Checkout\CancelCheckoutSession;
use App\Actions\Checkout\CreateCheckoutSession;
use App\Http\Api\ApiResponse;
use App\Http\Requests\Api\ListRequest;
use App\Http\Requests\Api\StoreCheckoutSessionRequest;
use App\Http\Resources\CheckoutSessionResource;
use App\Models\CheckoutSession;
use Illuminate\Http\JsonResponse;

class CheckoutSessionController extends ApiController
{
    public function index(ListRequest $request): JsonResponse
    {
        $sessions = $this->page(
            CheckoutSession::query()->forAccount($this->account()->merchant, $this->account()->livemode)->with(CheckoutSessionResource::RELATIONS),
            $request,
        );

        return ApiResponse::success(ApiResponse::RETRIEVED, CheckoutSessionResource::collection($sessions)->resolve($request));
    }

    public function store(StoreCheckoutSessionRequest $request, CreateCheckoutSession $createCheckoutSession): JsonResponse
    {
        $session = $createCheckoutSession->handle($this->account(), $request->toData());

        return ApiResponse::success(ApiResponse::CREATED, CheckoutSessionResource::make($session)->resolve($request));
    }

    public function show(string $sessionId): JsonResponse
    {
        return $this->respond($this->findOwned(CheckoutSession::class, $sessionId, with: CheckoutSessionResource::RELATIONS));
    }

    public function showByReference(string $clientReferenceId): JsonResponse
    {
        return $this->respond($this->findOwned(CheckoutSession::class, $clientReferenceId, 'client_reference_id', CheckoutSessionResource::RELATIONS));
    }

    public function showByInvoice(string $invoice): JsonResponse
    {
        return $this->respond($this->findOwned(CheckoutSession::class, $invoice, 'invoice', CheckoutSessionResource::RELATIONS));
    }

    public function cancel(string $sessionId, CancelCheckoutSession $cancelCheckoutSession): JsonResponse
    {
        $session = $cancelCheckoutSession->handle($this->findOwned(CheckoutSession::class, $sessionId));

        return ApiResponse::success(ApiResponse::UPDATED, CheckoutSessionResource::make($session)->resolve());
    }

    private function respond(CheckoutSession $session): JsonResponse
    {
        return ApiResponse::success(ApiResponse::RETRIEVED, CheckoutSessionResource::make($session)->resolve());
    }
}
