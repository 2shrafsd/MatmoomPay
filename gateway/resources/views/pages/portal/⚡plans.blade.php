<?php

use App\Enums\SubscriptionStatus;
use App\Actions\Audit\RecordAuditLog;
use App\Actions\Subscriptions\CreatePlan;
use App\Enums\PlanInterval;
use App\Livewire\PortalComponent;
use App\Models\Plan;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('Plans')] class extends PortalComponent {
    public string $name = '';

    public string $amount = '';

    public string $interval = 'month';

    public int $intervalCount = 1;

    public function create(CreatePlan $createPlan): void
    {
        $this->authorize('managePaymentLinks', $this->merchant());

        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'string', Money::amountRule()],
            'interval' => ['required', Rule::enum(PlanInterval::class)],
            'intervalCount' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $amount = Money::toMinorUnits($this->amount);

        if ($amount < 100) {
            $this->addError('amount', __('The amount must be at least :amount.', ['amount' => \App\Support\Money::format(100)]));

            return;
        }

        $createPlan->handle($this->account(), $this->name, $amount, PlanInterval::from($this->interval), $this->intervalCount, Auth::user());

        $this->reset('name', 'amount', 'interval', 'intervalCount');
        unset($this->plans);
        Flux::modal('create-plan')->close();
        Flux::toast(variant: 'success', text: __('Plan created.'));
    }

    public function toggle(string $planId, RecordAuditLog $recordAuditLog): void
    {
        $this->authorize('managePaymentLinks', $this->merchant());

        $plan = Plan::query()->forAccount($this->merchant(), $this->livemode())->where('public_id', $planId)->firstOrFail();
        $plan->update(['is_active' => ! $plan->is_active]);
        $recordAuditLog->handle(Auth::user(), $plan->is_active ? 'plan.activated' : 'plan.archived', $this->merchant(), $plan);

        unset($this->plans);
    }

    /**
     * @return Collection<int, Plan>
     */
    #[Computed]
    public function plans(): Collection
    {
        return Plan::query()->forAccount($this->merchant(), $this->livemode())->withCount(['subscriptions' => fn ($query) => $query->where('status', '!=', SubscriptionStatus::Cancelled)])->latest('id')->get();
    }

    public function with(): array
    {
        return ['canManage' => $this->role()->canManagePaymentLinks()];
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Plans')" :subheading="__('A plan sets the price and how often subscribers are charged. Existing subscribers keep their plan if you archive it.')">
        @if ($canManage)
            <x-slot:actions>
                <flux:modal.trigger name="create-plan">
                    <flux:button variant="primary" icon="plus">{{ __('New plan') }}</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
        @endif
    </x-portal.page-header>

    <section class="surface">
        @if ($this->plans->isEmpty())
            <x-portal.empty icon="rectangle-stack" :heading="__('No plans yet')" :body="__('Create a plan such as a monthly membership or a yearly licence.')" />
        @else
            <div class="data-table">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Plan') }}</flux:table.column>
                        <flux:table.column>{{ __('Price') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Active subscribers') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Plan ID') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->plans as $plan)
                            <flux:table.row :key="$plan->public_id">
                                <flux:table.cell variant="strong">
                                    {{ $plan->name }}
                                    @unless ($plan->is_active)
                                        <flux:badge size="sm" color="zinc" class="ms-1">{{ __('Archived') }}</flux:badge>
                                    @endunless
                                </flux:table.cell>
                                <flux:table.cell><span class="ltr-nums">{{ Money::format($plan->amount, $plan->currency) }}</span> {{ $plan->billingLabel() }}</flux:table.cell>
                                <flux:table.cell class="max-sm:hidden">{{ $plan->subscriptions_count }}</flux:table.cell>
                                <flux:table.cell class="max-md:hidden"><code class="font-mono text-xs" dir="ltr">{{ $plan->public_id }}</code></flux:table.cell>
                                <flux:table.cell class="text-end">
                                    @if ($canManage)
                                        @if ($plan->is_active)
                                            <flux:button size="sm" variant="ghost" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Archive :plan? Existing subscriptions keep renewing, but no new customer can subscribe.', ['plan' => $plan->name])) }}, run: () => $wire.toggle('{{ $plan->public_id }}') })">{{ __('Archive') }}</flux:button>
                                        @else
                                            <flux:button size="sm" variant="ghost" wire:click="toggle('{{ $plan->public_id }}')">{{ __('Restore') }}</flux:button>
                                        @endif
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>

    @if ($canManage)
        <flux:modal name="create-plan" class="w-full max-w-md">
            <form wire:submit="create" class="space-y-5">
                <flux:heading size="lg">{{ __('New plan') }}</flux:heading>
                <flux:input wire:model="name" :label="__('Plan name')" :placeholder="__('For example: Gold membership')" maxlength="80" required />
                <flux:input wire:model="amount" :label="__('Price (:currency)', ['currency' => Money::currency()])" inputmode="decimal" dir="ltr" :placeholder="Money::example()" required />
                <div class="grid grid-cols-2 gap-3">
                    <flux:input wire:model="intervalCount" type="number" min="1" max="12" :label="__('Every')" dir="ltr" required />
                    <flux:select wire:model="interval" :label="__('Period')">
                        <flux:select.option value="week">{{ __('Week') }}</flux:select.option>
                        <flux:select.option value="month">{{ __('Month') }}</flux:select.option>
                        <flux:select.option value="year">{{ __('Year') }}</flux:select.option>
                    </flux:select>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Create plan') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
