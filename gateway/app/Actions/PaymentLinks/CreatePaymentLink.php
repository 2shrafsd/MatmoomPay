<?php

declare(strict_types=1);

namespace App\Actions\PaymentLinks;

use App\Actions\Audit\RecordAuditLog;
use App\Data\PaymentLinkData;
use App\Models\PaymentLink;
use App\Models\User;
use App\Support\GatewayAccount;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

final readonly class CreatePaymentLink
{
    public function __construct(private RecordAuditLog $recordAuditLog) {}

    public function handle(GatewayAccount $account, PaymentLinkData $data, User $actor): PaymentLink
    {
        $minimum = Money::minimumAmount();

        if ($data->amount !== null && $data->amount < $minimum) {
            throw ValidationException::withMessages(['amount' => __('The amount must be at least :amount.', ['amount' => Money::format($minimum)])]);
        }

        if ($data->amount === null && $data->minAmount !== null && $data->minAmount < $minimum) {
            throw ValidationException::withMessages(['minAmount' => __('The amount must be at least :amount.', ['amount' => Money::format($minimum)])]);
        }

        $link = PaymentLink::create([
            'merchant_id' => $account->merchant->id,
            'livemode' => $account->livemode,
            'title' => $data->title,
            'description' => $data->description,
            'amount' => $data->amount,
            'min_amount' => $data->amount === null ? $data->minAmount : null,
            'max_amount' => $data->amount === null ? $data->maxAmount : null,
            'currency' => (string) config('gateway.currency'),
            'is_active' => true,
            'max_uses' => $data->maxUses,
            'collect_phone' => $data->collectPhone,
            'collect_email' => $data->collectEmail,
            'expires_at' => $data->expiresAt,
            'created_by' => $actor->id,
        ]);

        $this->recordAuditLog->handle($actor, 'payment_link.created', $account->merchant, $link);

        return $link;
    }
}
