<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\GatewayAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Transaction filters shared by the portal list and the CSV export. Every value is treated as
 * untrusted: unknown statuses and malformed dates are ignored rather than passed to the query.
 */
final readonly class PaymentSearch
{
    public function __construct(
        private GatewayAccount $account,
        private string $search = '',
        private string $status = '',
        private string $from = '',
        private string $to = '',
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(GatewayAccount $account, array $input): self
    {
        return new self(
            $account,
            is_string($input['q'] ?? null) ? $input['q'] : '',
            is_string($input['status'] ?? null) ? $input['status'] : '',
            is_string($input['from'] ?? null) ? $input['from'] : '',
            is_string($input['to'] ?? null) ? $input['to'] : '',
        );
    }

    /**
     * @return Builder<Payment>
     */
    public function query(): Builder
    {
        $status = PaymentStatus::tryFrom($this->status);
        $search = trim(mb_substr($this->search, 0, 64));
        $from = self::date($this->from);
        $to = self::date($this->to);

        return Payment::query()
            ->forAccount($this->account->merchant, $this->account->livemode)
            ->with(['checkoutSession:id,invoice,client_reference_id', 'paymentIntent:id,public_id,client_reference_id'])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn (Builder $query) => $query->where('created_at', '<=', $to->endOfDay()))
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('public_id', $search)
                        ->orWhere('card_last_four', $search)
                        ->orWhereHas('checkoutSession', fn (Builder $session) => $session->where('invoice', $search)->orWhere('client_reference_id', $search))
                        ->orWhereHas('paymentIntent', fn (Builder $intent) => $intent->where('client_reference_id', $search));
                });
            })
            ->latest('id');
    }

    private static function date(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== null && $date->format('Y-m-d') === $value ? $date : null;
    }
}
