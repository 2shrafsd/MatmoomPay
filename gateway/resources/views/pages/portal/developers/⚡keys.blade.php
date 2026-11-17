<?php

use App\Actions\ApiKeys\CreateApiKey;
use App\Actions\ApiKeys\RevokeApiKey;
use App\Enums\ApiKeyType;
use App\Livewire\PortalComponent;
use App\Models\ApiKey;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('API keys')] class extends PortalComponent {
    public string $name = '';

    public string $type = 'secret';

    /**
     * A freshly created secret, shown once and cleared as soon as the dialog closes.
     */
    public ?string $revealedToken = null;

    public function create(CreateApiKey $createApiKey): void
    {
        $this->authorize('manageDevelopers', $this->merchant());

        if ($this->livemode() && ! $this->merchant()->status->canProcessLivePayments()) {
            abort(403);
        }

        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::enum(ApiKeyType::class)],
        ]);

        [, $token] = $createApiKey->handle($this->merchant(), $this->livemode(), ApiKeyType::from($this->type), $this->name, Auth::user());

        $this->reset('name');
        Flux::modal('create-key')->close();

        if ($this->type === ApiKeyType::Secret->value) {
            $this->revealedToken = $token;
            Flux::modal('reveal-key')->show();
        } else {
            Flux::toast(variant: 'success', text: __('Publishable key created.'));
        }

        unset($this->keys);
    }

    public function dismissToken(): void
    {
        $this->revealedToken = null;
    }

    public function revoke(string $keyId, RevokeApiKey $revokeApiKey): void
    {
        $key = ApiKey::query()->forAccount($this->merchant(), $this->livemode())->where('public_id', $keyId)->firstOrFail();
        $this->authorize('revoke', $key);

        $revokeApiKey->handle($key, Auth::user());

        unset($this->keys);
        Flux::toast(variant: 'success', text: __('The key was revoked. Requests that use it are now refused.'));
    }

    /**
     * @return Collection<int, ApiKey>
     */
    #[Computed]
    public function keys(): Collection
    {
        return ApiKey::query()->forAccount($this->merchant(), $this->livemode())->active()->with('creator:id,name')->latest('id')->get();
    }

    public function with(): array
    {
        return [
            'canManage' => $this->role()->canManageDevelopers(),
            'livemode' => $this->livemode(),
            'liveBlocked' => $this->livemode() === false && ! $this->merchant()->status->canProcessLivePayments(),
        ];
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('API keys')" :subheading="$livemode ? __('Live keys move real money. Keep them on your server only.') : __('Test keys work with test cards only and never move real money.')">
        @if ($canManage)
            <x-slot:actions>
                <flux:modal.trigger name="create-key">
                    <flux:button variant="primary" icon="plus" data-test="new-key">{{ __('Create key') }}</flux:button>
                </flux:modal.trigger>
            </x-slot:actions>
        @endif
    </x-portal.page-header>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="surface p-5">
            <flux:heading>{{ __('API base URL') }}</flux:heading>
            <x-portal.copy :value="url('/api/v1')" class="mt-3" />
            <flux:text class="mt-3 text-sm">{{ __('Send your secret key in the matmoom-api-key header with every request.') }}</flux:text>
        </section>
        <section class="surface p-5">
            <flux:heading>{{ __('Hosted payment page') }}</flux:heading>
            <x-portal.copy :value="url('/pay').'/{session_id}?key={publishable_key}'" class="mt-3" />
            <flux:text class="mt-3 text-sm">{{ __('Redirect your customer here after you create a checkout session.') }} <flux:link :href="route('docs')" target="_blank">{{ __('Read the docs') }}</flux:link></flux:text>
        </section>
    </div>

    @if ($liveBlocked)
        <flux:callout icon="information-circle" class="mt-4" :heading="__('Live keys open after approval')">
            <flux:callout.text>{{ __('Build and test your integration with test keys now. Live keys become available once your account is approved.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <section class="surface mt-6">
        @if ($this->keys->isEmpty())
            <x-portal.empty icon="key" :heading="__('No active keys in this mode')" :body="__('Create a secret key for your server and a publishable key for the payment page link.')" />
        @else
            <div class="data-table">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Key') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Last used') }}</flux:table.column>
                        <flux:table.column class="max-lg:hidden">{{ __('Created by') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->keys as $key)
                            <flux:table.row :key="$key->public_id">
                                <flux:table.cell variant="strong">
                                    {{ $key->name }}
                                    <flux:badge size="sm" class="ms-1" :color="$key->type === ApiKeyType::Secret ? 'red' : 'sky'">{{ $key->type->label() }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="min-w-64">
                                    @if ($key->type === ApiKeyType::Publishable)
                                        <x-portal.copy :value="$key->publishable_token" />
                                    @else
                                        <code class="ltr-nums font-mono text-xs" dir="ltr">{{ $key->maskedToken() }}</code>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="max-md:hidden">{{ $key->last_used_at?->locale(app()->getLocale())->diffForHumans() ?? __('Never') }}</flux:table.cell>
                                <flux:table.cell class="max-lg:hidden">{{ $key->creator?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell class="text-end">
                                    @if ($canManage)
                                        <flux:button size="sm" variant="ghost" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Revoke :name? Any system using this key stops working immediately.', ['name' => $key->name])) }}, danger: true, run: () => $wire.revoke('{{ $key->public_id }}') })">{{ __('Revoke') }}</flux:button>
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
        <flux:modal name="create-key" class="w-full max-w-md">
            <form wire:submit="create" class="space-y-5">
                <flux:heading size="lg">{{ $livemode ? __('Create a live key') : __('Create a test key') }}</flux:heading>
                <flux:input wire:model="name" :label="__('Name')" :placeholder="__('For example: Online store server')" required />
                <flux:radio.group wire:model="type" :label="__('Type')">
                    <flux:radio value="secret" :label="__('Secret key')" :description="__('For your server. Creates sessions, refunds and reads payments.')" />
                    <flux:radio value="publishable" :label="__('Publishable key')" :description="__('Safe to put in the payment page link.')" />
                </flux:radio.group>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Create key') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="reveal-key" class="w-full max-w-lg" :dismissible="false" wire:close="dismissToken">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Copy your secret key now') }}</flux:heading>
                <flux:callout icon="exclamation-triangle" color="amber">
                    <flux:callout.text>{{ __('This is the only time the full key is shown. Store it in your server environment, never in an app or website code.') }}</flux:callout.text>
                </flux:callout>
                @if ($revealedToken)
                    <x-portal.copy :value="$revealedToken" data-test="revealed-key" />
                @endif
                <div class="flex justify-end">
                    <flux:modal.close><flux:button variant="primary">{{ __('I saved it') }}</flux:button></flux:modal.close>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
