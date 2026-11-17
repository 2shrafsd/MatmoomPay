<?php

use App\Actions\PaymentLinks\CreatePaymentLink;
use App\Data\PaymentLinkData;
use App\Livewire\PortalComponent;
use App\Models\PaymentLink;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

new #[Title('Payment links')] class extends PortalComponent {
    use WithPagination;

    public string $title = '';
    public string $description = '';
    public string $amountType = 'fixed';
    public string $amount = '';
    public string $minAmount = '';
    public string $maxAmount = '';
    public string $maxUses = '';
    public string $expiresAt = '';
    public bool $collectPhone = true;
    public bool $collectEmail = false;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $money = ['string', Money::amountRule()];

        return [
            'title' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'amountType' => ['required', 'in:fixed,open'],
            'amount' => ['required_if:amountType,fixed', 'nullable', ...$money],
            'minAmount' => ['nullable', ...$money],
            'maxAmount' => ['nullable', ...$money],
            'maxUses' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'expiresAt' => ['nullable', 'date_format:Y-m-d', 'after:today'],
            'collectPhone' => ['boolean'],
            'collectEmail' => ['boolean'],
        ];
    }

    public function create(CreatePaymentLink $createPaymentLink): void
    {
        $this->authorize('managePaymentLinks', $this->merchant());
        $this->validate();

        $fixed = $this->amountType === 'fixed';
        $amount = $fixed ? Money::toMinorUnits($this->amount) : null;
        $min = ! $fixed && $this->minAmount !== '' ? Money::toMinorUnits($this->minAmount) : null;
        $max = ! $fixed && $this->maxAmount !== '' ? Money::toMinorUnits($this->maxAmount) : null;

        if ($fixed && $amount < 100) {
            $this->addError('amount', __('The amount must be at least :amount.', ['amount' => \App\Support\Money::format(100)]));

            return;
        }

        if ($min !== null && $max !== null && $min > $max) {
            $this->addError('maxAmount', __('The maximum must be larger than the minimum.'));

            return;
        }

        $link = $createPaymentLink->handle($this->account(), new PaymentLinkData(
            title: $this->title,
            description: $this->description !== '' ? $this->description : null,
            amount: $amount,
            minAmount: $min,
            maxAmount: $max,
            maxUses: $this->maxUses !== '' ? (int) $this->maxUses : null,
            collectPhone: $this->collectPhone,
            collectEmail: $this->collectEmail,
            expiresAt: $this->expiresAt !== '' ? CarbonImmutable::createFromFormat('Y-m-d', $this->expiresAt)->endOfDay() : null,
        ), Auth::user());

        Flux::modal('create-link')->close();
        Flux::toast(variant: 'success', text: __('Your payment link is ready to share.'));

        $this->redirectRoute('portal.links.show', $link, navigate: true);
    }

    /**
     * @return LengthAwarePaginator<int, PaymentLink>
     */
    #[Computed]
    public function links(): LengthAwarePaginator
    {
        return PaymentLink::query()->forAccount($this->merchant(), $this->livemode())->latest('id')->paginate(20);
    }

    #[Computed]
    public function canManage(): bool
    {
        return $this->role()->canManagePaymentLinks();
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Payment links')" :subheading="__('Create a link once and share it anywhere: WhatsApp, Instagram, email or a printed QR code.')">
        @if ($this->canManage)
            <x-slot:actions>
                <flux:modal.trigger name="create-link">
                    <flux:button variant="primary" icon="plus" data-test="new-link">{{ __('New payment link') }}</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
        @endif
    </x-portal.page-header>

    <section class="surface transition-opacity" wire:loading.class.delay="opacity-60" wire:loading.attr.delay="aria-busy">
        @if ($this->links->isEmpty())
            <x-portal.empty icon="link" :heading="__('No payment links yet')" :body="__('A payment link is the quickest way to get paid without a website.')" />
        @else
            <div class="data-table">
                <flux:table :paginate="$this->links">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Title') }}</flux:table.column>
                        <flux:table.column>{{ __('Amount') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Paid') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Created') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->links as $link)
                            <flux:table.row :key="$link->public_id">
                                <flux:table.cell variant="strong"><a href="{{ route('portal.links.show', $link) }}" wire:navigate class="hover:underline">{{ $link->title }}</a></flux:table.cell>
                                <flux:table.cell><span class="ltr-nums">{{ $link->amount ? Money::format($link->amount, $link->currency) : __('Customer chooses') }}</span></flux:table.cell>
                                <flux:table.cell class="max-sm:hidden">{{ $link->uses_count }}@if ($link->max_uses) / {{ $link->max_uses }}@endif</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" inset="top bottom" :color="$link->isAcceptingPayments() ? 'green' : 'zinc'">{{ $link->isAcceptingPayments() ? __('Active') : __('Closed') }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="max-md:hidden">{{ $link->created_at->locale(app()->getLocale())->translatedFormat('j M Y') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>

    @if ($this->canManage)
        <flux:modal name="create-link" class="w-full max-w-lg">
            <form wire:submit="create" class="space-y-5">
                <div>
                    <flux:heading size="lg">{{ __('New payment link') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Customers open the link, enter their details and pay by card.') }}</flux:text>
                </div>

                <flux:input wire:model="title" :label="__('What is the payment for?')" :placeholder="__('For example: Eid sweets box')" maxlength="80" required />
                <flux:textarea wire:model="description" :label="__('Description')" rows="2" maxlength="500" />

                <flux:radio.group wire:model.live="amountType" :label="__('Amount')" variant="segmented">
                    <flux:radio value="fixed" :label="__('Fixed amount')" />
                    <flux:radio value="open" :label="__('Customer chooses')" />
                </flux:radio.group>

                @if ($amountType === 'fixed')
                    <flux:input wire:model="amount" :label="__('Amount (:currency)', ['currency' => Money::currency()])" inputmode="decimal" dir="ltr" :placeholder="Money::example()" required />
                @else
                    <div class="grid grid-cols-2 gap-3">
                        <flux:input wire:model="minAmount" :label="__('Minimum (optional)')" inputmode="decimal" dir="ltr" :placeholder="Money::example()" />
                        <flux:input wire:model="maxAmount" :label="__('Maximum (optional)')" inputmode="decimal" dir="ltr" :placeholder="Money::example()" />
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model="maxUses" type="number" min="1" :label="__('Number of payments (optional)')" dir="ltr" />
                    <flux:input wire:model="expiresAt" type="date" :label="__('Closes on (optional)')" />
                </div>

                <div class="space-y-2">
                    <flux:checkbox wire:model="collectPhone" :label="__('Ask for a phone number')" />
                    <flux:checkbox wire:model="collectEmail" :label="__('Ask for an email address')" />
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" data-test="create-link">{{ __('Create link') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
