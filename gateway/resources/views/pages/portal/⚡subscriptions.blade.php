<?php

use App\Actions\Subscriptions\CancelSubscription;
use App\Enums\SubscriptionStatus;
use App\Exceptions\GatewayException;
use App\Livewire\PortalComponent;
use App\Models\Subscription;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Title('Subscriptions')] class extends PortalComponent {
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function cancel(string $subscriptionId, bool $atPeriodEnd, CancelSubscription $cancelSubscription): void
    {
        $subscription = Subscription::query()->forAccount($this->merchant(), $this->livemode())->where('public_id', $subscriptionId)->firstOrFail();
        $this->authorize('refund', $this->merchant());

        try {
            $cancelSubscription->handle($subscription, $atPeriodEnd, Auth::user());
            Flux::toast(variant: 'success', text: $atPeriodEnd ? __('The subscription ends after the current period.') : __('The subscription was cancelled.'));
        } catch (GatewayException $exception) {
            Flux::toast(variant: 'danger', text: __($exception->getMessage()));
        }
    }

    /**
     * @return LengthAwarePaginator<int, Subscription>
     */
    #[Computed]
    public function subscriptions(): LengthAwarePaginator
    {
        $status = SubscriptionStatus::tryFrom($this->status);

        return Subscription::query()
            ->forAccount($this->merchant(), $this->livemode())
            ->with(['plan', 'customer', 'paymentMethod'])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20);
    }

    public function with(): array
    {
        return ['canManage' => $this->role()->canRefund()];
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Subscriptions')" :subheading="__('Customers who pay you on a schedule. Renewals are charged automatically to their saved card.')">
        <x-slot:actions>
            <flux:button :href="route('portal.plans')" icon="rectangle-stack" wire:navigate>{{ __('Plans') }}</flux:button>
        </x-slot:actions>
    </x-portal.page-header>

    <div class="surface mb-4 p-4 sm:max-w-xs">
        <flux:select wire:model.live="status" :label="__('Status')">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (SubscriptionStatus::cases() as $case)
                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <section class="surface transition-opacity" wire:loading.class.delay="opacity-60" wire:loading.attr.delay="aria-busy">
        @if ($this->subscriptions->isEmpty())
            <x-portal.empty icon="arrow-path" :heading="__('No subscriptions yet')" :body="__('Create a plan, then start a checkout session with mode subscription and the plan_id.')" />
        @else
            <div class="data-table">
                <flux:table :paginate="$this->subscriptions">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Customer') }}</flux:table.column>
                        <flux:table.column>{{ __('Plan') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Next billing') }}</flux:table.column>
                        <flux:table.column class="max-lg:hidden">{{ __('Card') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->subscriptions as $subscription)
                            <flux:table.row :key="$subscription->public_id">
                                <flux:table.cell variant="strong">
                                    @if ($subscription->customer)
                                        <a href="{{ route('portal.customers.show', $subscription->customer) }}" wire:navigate class="ltr-nums break-all hover:underline">{{ $subscription->customer->client_customer_id }}</a>
                                    @else
                                        <span class="text-zinc-500">{{ __('Deleted customer') }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    {{ $subscription->plan->name }}
                                    <span class="block text-xs text-zinc-500"><span class="ltr-nums">{{ Money::format($subscription->plan->amount, $subscription->plan->currency) }}</span> {{ $subscription->plan->billingLabel() }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-portal.status :status="$subscription->status" />
                                    @if ($subscription->cancel_at_period_end && $subscription->status !== SubscriptionStatus::Cancelled)
                                        <span class="block text-xs text-zinc-500">{{ __('Ends at period end') }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="max-md:hidden">{{ $subscription->status === SubscriptionStatus::Cancelled ? '—' : $subscription->current_period_end->locale(app()->getLocale())->translatedFormat('j M Y') }}</flux:table.cell>
                                <flux:table.cell class="max-lg:hidden"><span class="ltr-nums">{{ $subscription->paymentMethod ? '•••• '.$subscription->paymentMethod->last_four : '—' }}</span></flux:table.cell>
                                <flux:table.cell class="text-end whitespace-nowrap">
                                    @if ($canManage && $subscription->status !== SubscriptionStatus::Cancelled)
                                        <flux:dropdown align="end">
                                            <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" :aria-label="__('Actions')" />
                                            <flux:menu>
                                                @unless ($subscription->cancel_at_period_end)
                                                    <flux:menu.item x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Stop renewing this subscription after the current period?')) }}, danger: true, run: () => $wire.cancel('{{ $subscription->public_id }}', true) })">{{ __('Cancel at period end') }}</flux:menu.item>
                                                @endunless
                                                <flux:menu.item variant="danger" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Cancel this subscription now? The customer will not be charged again.')) }}, danger: true, run: () => $wire.cancel('{{ $subscription->public_id }}', false) })">{{ __('Cancel now') }}</flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>
</div>
