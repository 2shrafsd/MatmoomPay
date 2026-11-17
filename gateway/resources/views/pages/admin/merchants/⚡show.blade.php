<?php

use App\Actions\ApiKeys\RevokeApiKey;
use App\Actions\Merchants\ArchiveMerchant;
use App\Actions\Merchants\ChangeMerchantStatus;
use App\Actions\Merchants\ManageMerchantDocuments;
use App\Actions\Merchants\UpdateMerchantProfile;
use App\Actions\Settlements\CreateSettlement;
use App\Actions\Team\UpdateTeamMember;
use App\Data\MerchantProfileData;
use App\Enums\DocumentStatus;
use App\Enums\MerchantRole;
use App\Enums\MerchantStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\GatewayException;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\BalanceTransaction;
use App\Models\Merchant;
use App\Models\Payment;
use App\Support\BusinessActivities;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::admin'), Title('Merchant review')] class extends Component {
    public Merchant $merchant;

    public string $reason = '';

    public string $feeRate = '';

    public string $feeFixed = '';

    public string $documentNote = '';

    /**
     * @var array{name: string, legal_name: string, activity: string, commercial_registration: string, email: string, phone: string, website: string}
     */
    public array $profile = [
        'name' => '', 'legal_name' => '', 'activity' => '', 'commercial_registration' => '', 'email' => '', 'phone' => '', 'website' => '',
    ];

    public function mount(Merchant $merchant): void
    {
        $this->merchant = $merchant;
        $this->fillProfile();
        $this->feeRate = rtrim(rtrim(number_format($merchant->fee_rate_bps / 100, 2, '.', ''), '0'), '.');
        $this->feeFixed = Money::decimal($merchant->fee_fixed);
    }

    public function changeStatus(string $status, ChangeMerchantStatus $changeMerchantStatus): void
    {
        $target = MerchantStatus::tryFrom($status) ?? abort(422);

        if ($target->requiresReason()) {
            $this->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => __('Write a reason. The merchant will see it.')]);
        }

        try {
            $changeMerchantStatus->handle($this->merchant, $target, Auth::user(), $this->reason !== '' ? $this->reason : null);
        } catch (GatewayException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->reason = '';

        Flux::modals()->close();
        Flux::toast(variant: 'success', text: __('Merchant status changed to :status.', ['status' => $target->label()]));
    }

    public function updatePricing(ChangeMerchantStatus $changeMerchantStatus): void
    {
        $this->validate([
            'feeRate' => ['required', 'numeric', 'min:0', 'max:10'],
            'feeFixed' => ['required', 'string', Money::amountRule()],
        ]);

        $changeMerchantStatus->updatePricing($this->merchant, (int) round((float) $this->feeRate * 100), Money::toMinorUnits($this->feeFixed), Auth::user());

        Flux::toast(variant: 'success', text: __('Pricing updated. It applies to new payments only.'));
    }

    public function verifySettlementAccount(UpdateMerchantProfile $updateMerchantProfile, string $reviewedFingerprint = ''): void
    {
        try {
            $updateMerchantProfile->verifySettlementAccount($this->merchant, Auth::user(), $reviewedFingerprint);
        } catch (GatewayException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->merchant->refresh();

        Flux::toast(variant: 'success', text: __('Settlement account verified. Payouts can go to it now.'));
    }

    public function settle(CreateSettlement $createSettlement): void
    {
        try {
            $createSettlement->handle($this->merchant, Auth::user());
            Flux::toast(variant: 'success', text: __('Settlement created. Mark it as transferred once the bank confirms.'));
        } catch (GatewayException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function reviewDocument(string $documentId, string $decision, ManageMerchantDocuments $documents): void
    {
        $document = $this->merchant->documents()->where('public_id', $documentId)->firstOrFail();
        $status = $decision === 'approve' ? DocumentStatus::Approved : DocumentStatus::Rejected;

        if ($status === DocumentStatus::Rejected) {
            $this->validate(['documentNote' => ['required', 'string', 'max:500']], ['documentNote.required' => __('Write what is wrong with the document. The merchant will see it.')]);
        }

        $documents->review($document, $status, $status === DocumentStatus::Rejected ? $this->documentNote : null, Auth::user());
        $this->documentNote = '';

        Flux::toast(variant: 'success', text: $status === DocumentStatus::Approved ? __('Document accepted.') : __('Document rejected.'));
    }

    private function fillProfile(): void
    {
        $this->profile = [
            'name' => $this->merchant->name,
            'legal_name' => $this->merchant->legal_name,
            'activity' => $this->merchant->activity,
            'commercial_registration' => (string) $this->merchant->commercial_registration,
            'email' => $this->merchant->email,
            'phone' => $this->merchant->phone,
            'website' => (string) $this->merchant->website,
        ];
    }

    public function updateProfile(UpdateMerchantProfile $updateMerchantProfile): void
    {
        $validated = $this->validate([
            'profile.name' => ['required', 'string', 'max:80'],
            'profile.legal_name' => ['required', 'string', 'max:120'],
            'profile.activity' => ['required', Rule::in([...array_values(BusinessActivities::all()), $this->merchant->activity])],
            'profile.commercial_registration' => ['nullable', 'string', 'max:40'],
            'profile.email' => ['required', 'email', 'max:120'],
            'profile.phone' => ['required', 'string', 'max:30'],
            'profile.website' => ['nullable', 'url:http,https', 'max:200'],
        ])['profile'];

        $updateMerchantProfile->handle($this->merchant, MerchantProfileData::fromArray([
            ...$validated,
            'commercial_registration' => filled($validated['commercial_registration']) ? $validated['commercial_registration'] : null,
            'website' => filled($validated['website']) ? $validated['website'] : null,
        ]), Auth::user());

        Flux::modal('edit-merchant')->close();
        Flux::toast(variant: 'success', text: __('Merchant details saved.'));
    }

    public function archive(ArchiveMerchant $archiveMerchant): void
    {
        $this->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => __('Write a reason. The merchant will see it.')]);

        $archiveMerchant->archive($this->merchant, Auth::user(), $this->reason);
        $this->reason = '';

        Flux::modals()->close();
        Flux::toast(variant: 'success', text: __(':name was archived. Its API keys no longer work.', ['name' => $this->merchant->name]));
    }

    public function restore(ArchiveMerchant $archiveMerchant): void
    {
        $archiveMerchant->restore($this->merchant, Auth::user());

        Flux::toast(variant: 'success', text: __(':name is back. The team needs to create new API keys.', ['name' => $this->merchant->name]));
    }

    public function destroy(ArchiveMerchant $archiveMerchant): void
    {
        try {
            $archiveMerchant->destroy($this->merchant, Auth::user());
        } catch (GatewayException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __(':name was deleted permanently.', ['name' => $this->merchant->name]));
        $this->redirectRoute('admin.merchants', navigate: true);
    }

    public function revokeKey(string $keyId, RevokeApiKey $revokeApiKey): void
    {
        $key = ApiKey::query()->where('merchant_id', $this->merchant->id)->where('public_id', $keyId)->firstOrFail();
        $revokeApiKey->handle($key, Auth::user());

        Flux::toast(variant: 'success', text: __('API key revoked.'));
    }

    public function revokeAllKeys(RevokeApiKey $revokeApiKey): void
    {
        $keys = ApiKey::query()->where('merchant_id', $this->merchant->id)->whereNull('revoked_at')->get();

        foreach ($keys as $key) {
            $revokeApiKey->handle($key, Auth::user());
        }

        Flux::toast(variant: 'success', text: trans_choice(':count key revoked.|:count keys revoked.', $keys->count(), ['count' => $keys->count()]));
    }

    public function changeMemberRole(int $userId, string $role, UpdateTeamMember $updateTeamMember): void
    {
        $member = $this->merchant->users()->whereKey($userId)->firstOrFail();

        try {
            $updateTeamMember->changeRole($this->merchant, $member, MerchantRole::from($role), Auth::user());
        } catch (GatewayException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Role updated.'));
    }

    public function removeMember(int $userId, UpdateTeamMember $updateTeamMember): void
    {
        $member = $this->merchant->users()->whereKey($userId)->firstOrFail();

        try {
            $updateTeamMember->remove($this->merchant, $member, Auth::user());
        } catch (GatewayException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __(':name was removed from the team.', ['name' => $member->name]));
    }

    public function with(): array
    {
        $live = Payment::query()->where('merchant_id', $this->merchant->id)->where('livemode', true);

        return [
            'members' => $this->merchant->users()->orderBy('name')->get(),
            'liveVolume' => (int) (clone $live)->where('status', PaymentStatus::Successful)->sum('amount'),
            'livePayments' => (clone $live)->where('status', PaymentStatus::Successful)->count(),
            'unsettled' => (int) BalanceTransaction::query()->where('merchant_id', $this->merchant->id)->where('livemode', true)->whereNull('settlement_id')->sum('amount'),
            'documents' => $this->merchant->documents()->with('uploader:id,name')->latest('id')->get(),
            'apiKeys' => ApiKey::query()->where('merchant_id', $this->merchant->id)->latest('id')->limit(30)->get(),
            'canDeletePermanently' => ! app(ArchiveMerchant::class)->hasMoneyHistory($this->merchant),
            'activity' => AuditLog::query()->where('merchant_id', $this->merchant->id)->with('actor:id,name')->latest('id')->limit(15)->get(),
        ];
    }
}; ?>

<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('admin.merchants')" wire:navigate>{{ __('Merchants') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $merchant->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <section class="surface p-5 sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <flux:heading size="xl" level="1">{{ $merchant->name }}</flux:heading>
                    <x-portal.status :status="$merchant->status" />
                </div>
                <flux:text class="mt-1">{{ $merchant->legal_name }} · {{ $merchant->activityLabel() }}</flux:text>
                @if ($merchant->status_reason)
                    <flux:text class="mt-2 text-sm">{{ __('Reason') }}: {{ $merchant->status_reason }}</flux:text>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($merchant->trashed())
                    <flux:button variant="primary" icon="arrow-uturn-left" wire:click="restore" data-test="restore-merchant">{{ __('Restore') }}</flux:button>
                @else
                    <flux:modal.trigger name="edit-merchant"><flux:button icon="pencil-square" data-test="edit-merchant">{{ __('Edit') }}</flux:button></flux:modal.trigger>
                    @if ($merchant->status !== MerchantStatus::Active)
                        <flux:button variant="primary" icon="check" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Approve :name for live payments?', ['name' => $merchant->name])) }}, danger: false, run: () => $wire.changeStatus('active') })" data-test="approve-merchant">{{ $merchant->status === MerchantStatus::Pending ? __('Approve') : __('Reactivate') }}</flux:button>
                    @endif
                    @if ($merchant->status === MerchantStatus::Pending)
                        <flux:modal.trigger name="reject"><flux:button icon="x-mark">{{ __('Reject') }}</flux:button></flux:modal.trigger>
                    @endif
                    @if ($merchant->status === MerchantStatus::Active)
                        <flux:modal.trigger name="suspend"><flux:button icon="pause-circle">{{ __('Disable (suspend)') }}</flux:button></flux:modal.trigger>
                    @endif
                @endif
                <flux:dropdown position="bottom" align="end">
                    <flux:button icon="ellipsis-horizontal" :aria-label="__('More actions')" data-test="merchant-more" />
                    <flux:menu>
                        @unless ($merchant->trashed())
                            @if ($merchant->status !== MerchantStatus::Banned)
                                <flux:modal.trigger name="ban"><flux:menu.item icon="no-symbol" variant="danger" data-test="ban-merchant">{{ __('Ban') }}</flux:menu.item></flux:modal.trigger>
                            @endif
                            <flux:modal.trigger name="archive"><flux:menu.item icon="archive-box" variant="danger">{{ __('Archive (delete)') }}</flux:menu.item></flux:modal.trigger>
                        @endunless
                        <flux:menu.item icon="trash" variant="danger" :disabled="! $canDeletePermanently" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Delete :name permanently? This cannot be undone. Merchants with payment history can only be archived.', ['name' => $merchant->name])) }}, danger: true, run: () => $wire.destroy() })" data-test="delete-merchant">{{ __('Delete permanently') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>

        @if ($merchant->trashed())
            <div class="mt-4 flex items-center gap-3 rounded-2xl bg-zinc-100 px-4 py-3 text-sm text-zinc-700" role="status">
                <flux:icon.archive-box variant="mini" />
                {{ __('Archived on :date. The merchant cannot sign in to its portal or use the API until you restore it.', ['date' => $merchant->deleted_at->locale(app()->getLocale())->translatedFormat('j M Y')]) }}
            </div>
        @elseif ($merchant->status === MerchantStatus::Banned)
            <div class="mt-4 flex items-center gap-3 rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-800 ring-1 ring-red-200" role="status">
                <flux:icon.no-symbol variant="mini" />
                {{ __('Banned. Payments, API keys and portal access are all blocked.') }}
            </div>
        @endif

        <dl class="mt-6 grid gap-x-6 gap-y-4 border-t border-zinc-200 pt-6 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-zinc-500">{{ __('Merchant ID') }}</dt><dd class="ltr-nums mt-0.5 text-start font-mono">{{ $merchant->public_id }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Commercial registration number') }}</dt><dd class="ltr-nums mt-0.5 text-start">{{ $merchant->commercial_registration ?? '—' }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Business email') }}</dt><dd class="ltr-nums mt-0.5 text-start break-all">{{ $merchant->email }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Phone number') }}</dt><dd class="ltr-nums mt-0.5 text-start">{{ $merchant->phone }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Website') }}</dt><dd class="ltr-nums mt-0.5 text-start break-all">{{ $merchant->website ?? '—' }}</dd></div>
            <div>
                <dt class="text-zinc-500">{{ __('Settlement account') }}</dt>
                <dd class="mt-0.5 flex flex-wrap items-center gap-2">
                    <span class="ltr-nums">{{ $merchant->settlement_bank_name ? $merchant->settlement_bank_name.' · '.$merchant->maskedIban() : '—' }}</span>
                    @if ($merchant->settlement_iban)
                        @if ($merchant->settlement_account_verified_at)
                            <flux:badge size="sm" color="green">{{ __('Verified') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="amber">{{ __('Needs verification') }}</flux:badge>
                            <flux:button size="xs" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Confirm you checked this account belongs to :name?', ['name' => $merchant->legal_name])) }}, danger: false, run: () => $wire.verifySettlementAccount({{ \Illuminate\Support\Js::from($merchant->settlementAccountFingerprint()) }}) })" data-test="verify-settlement-account">{{ __('Verify') }}</flux:button>
                        @endif
                    @endif
                </dd>
            </div>
            <div><dt class="text-zinc-500">{{ __('Applied') }}</dt><dd class="mt-0.5">{{ $merchant->created_at->locale(app()->getLocale())->translatedFormat('j M Y · H:i') }}</dd></div>
            <div><dt class="text-zinc-500">{{ __('Approved') }}</dt><dd class="mt-0.5">{{ $merchant->approved_at?->locale(app()->getLocale())->translatedFormat('j M Y · H:i') ?? '—' }}</dd></div>
        </dl>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-portal.stat :label="__('Live volume')" :value="Money::format($liveVolume)" :hint="trans_choice(':count successful payment|:count successful payments', $livePayments, ['count' => $livePayments])" />
        <div class="surface flex flex-col gap-2 p-4 sm:p-5">
            <flux:text class="text-sm">{{ __('Unsettled balance') }}</flux:text>
            <p class="ltr-nums text-start text-2xl font-semibold">{{ Money::format($unsettled) }}</p>
            <flux:button size="sm" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Create a settlement for :amount?', ['amount' => Money::format($unsettled)])) }}, danger: false, run: () => $wire.settle() })" :disabled="$unsettled <= 0">{{ __('Create settlement') }}</flux:button>
        </div>
        <form wire:submit="updatePricing" class="surface grid gap-3 p-4 sm:p-5">
            <flux:text class="text-sm">{{ __('Pricing') }}</flux:text>
            <div class="grid grid-cols-2 gap-2">
                <flux:input wire:model="feeRate" :label="__('Rate %')" inputmode="decimal" dir="ltr" size="sm" />
                <flux:input wire:model="feeFixed" :label="__('Fixed (:currency)', ['currency' => Money::currency()])" inputmode="decimal" dir="ltr" size="sm" />
            </div>
            <flux:button size="sm" type="submit">{{ __('Save pricing') }}</flux:button>
        </form>
    </div>

    <section class="surface p-5" aria-labelledby="kyc-heading">
        <flux:heading id="kyc-heading">{{ __('Verification documents') }}</flux:heading>
        @if ($documents->isEmpty())
            <flux:text class="mt-3">{{ __('The merchant has not uploaded any documents yet.') }}</flux:text>
        @else
            <ul class="mt-3 divide-y divide-zinc-200 text-sm">
                @foreach ($documents as $document)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="kyc-{{ $document->public_id }}">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $document->type->label() }}</p>
                            <p class="truncate text-zinc-500"><span dir="ltr">{{ $document->original_name }}</span> · {{ $document->uploader?->name }} · {{ $document->created_at->locale(app()->getLocale())->diffForHumans() }}</p>
                            <p class="ltr-nums truncate text-start font-mono text-xs text-zinc-400" dir="ltr">sha256 {{ $document->sha256 }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="$document->status->color()">{{ $document->status->label() }}</flux:badge>
                            <flux:button size="sm" icon="arrow-down-tray" :href="route('documents.download', $document)">{{ __('Download') }}</flux:button>
                            @if ($document->status === DocumentStatus::Pending)
                                <flux:button size="sm" variant="primary" wire:click="reviewDocument('{{ $document->public_id }}', 'approve')">{{ __('Accept') }}</flux:button>
                                <flux:button size="sm" variant="danger" wire:click="reviewDocument('{{ $document->public_id }}', 'reject')">{{ __('Reject') }}</flux:button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            <flux:input wire:model="documentNote" class="mt-3" :label="__('Note for a rejected document')" :placeholder="__('For example: the certificate has expired')" />
        @endif
    </section>

    <section class="surface p-5" aria-labelledby="keys-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:heading id="keys-heading">{{ __('API keys') }}</flux:heading>
            @if ($apiKeys->whereNull('revoked_at')->isNotEmpty())
                <flux:button size="sm" variant="danger" icon="key" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Revoke every active API key of :name? Their integration stops until they create new keys.', ['name' => $merchant->name])) }}, danger: true, run: () => $wire.revokeAllKeys() })" data-test="revoke-all-keys">{{ __('Revoke all keys') }}</flux:button>
            @endif
        </div>
        @if ($apiKeys->isEmpty())
            <flux:text class="mt-3">{{ __('This merchant has not created any API keys yet.') }}</flux:text>
        @else
            <ul class="mt-3 divide-y divide-zinc-200 text-sm">
                @foreach ($apiKeys as $key)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-2.5" wire:key="key-{{ $key->public_id }}">
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ $key->name }}</span>
                            <span class="ltr-nums block truncate text-start font-mono text-xs text-zinc-500" dir="ltr">{{ $key->token_prefix }}…{{ $key->last_four }}</span>
                        </span>
                        <span class="flex flex-wrap items-center gap-2">
                            <flux:badge size="sm" :color="$key->livemode ? 'lime' : 'amber'">{{ $key->livemode ? __('Live') : __('Test') }}</flux:badge>
                            <span class="text-xs text-zinc-500">{{ $key->last_used_at ? __('Used :time', ['time' => $key->last_used_at->locale(app()->getLocale())->diffForHumans()]) : __('Never used') }}</span>
                            @if ($key->isRevoked())
                                <flux:badge size="sm" color="zinc">{{ __('Revoked') }}</flux:badge>
                            @else
                                <flux:button size="xs" variant="danger" wire:click="revokeKey('{{ $key->public_id }}')">{{ __('Revoke') }}</flux:button>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="surface p-5" aria-labelledby="members-heading">
            <flux:heading id="members-heading">{{ __('Team') }}</flux:heading>
            <ul class="mt-3 divide-y divide-zinc-200 text-sm">
                @foreach ($members as $member)
                    @php($memberRole = MerchantRole::from($member->pivot->role))
                    <li class="flex flex-wrap items-center justify-between gap-3 py-2.5" wire:key="m-{{ $member->id }}">
                        <span class="min-w-0">
                            <span class="flex items-center gap-2 truncate font-medium">{{ $member->name }}
                                @if ($member->isBanned())<flux:badge size="sm" color="red">{{ __('Banned') }}</flux:badge>@endif
                            </span>
                            <span class="ltr-nums block truncate text-start text-zinc-500">{{ $member->email }}</span>
                        </span>
                        <span class="flex items-center gap-2">
                            @if ($memberRole === MerchantRole::Owner)
                                <flux:badge size="sm" color="lime">{{ $memberRole->label() }}</flux:badge>
                            @else
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button size="xs" icon-trailing="chevron-down">{{ $memberRole->label() }}</flux:button>
                                    <flux:menu>
                                        @foreach (MerchantRole::assignable() as $role)
                                            <flux:menu.item wire:click="changeMemberRole({{ $member->id }}, '{{ $role->value }}')" :icon="$role === $memberRole ? 'check' : null">{{ $role->label() }}</flux:menu.item>
                                        @endforeach
                                        <flux:menu.separator />
                                        <flux:menu.item icon="user-minus" variant="danger" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Remove :name from :merchant?', ['name' => $member->name, 'merchant' => $merchant->name])) }}, danger: true, run: () => $wire.removeMember({{ $member->id }}) })">{{ __('Remove from team') }}</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                            <flux:button size="xs" variant="ghost" icon="arrow-top-right-on-square" :href="route('admin.users', ['q' => $member->email])" wire:navigate :aria-label="__('Open user account')" />
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="surface p-5" aria-labelledby="activity-heading">
            <flux:heading id="activity-heading">{{ __('Recent activity') }}</flux:heading>
            <ul class="mt-3 divide-y divide-zinc-200 text-sm">
                @forelse ($activity as $entry)
                    <li class="flex items-center justify-between gap-3 py-2.5" wire:key="a-{{ $entry->id }}">
                        <span><code class="font-mono text-xs" dir="ltr">{{ $entry->action }}</code> · {{ $entry->actor?->name ?? __('System') }}</span>
                        <span class="shrink-0 text-zinc-500">{{ $entry->created_at->locale(app()->getLocale())->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="py-2.5 text-zinc-500">{{ __('No activity recorded yet.') }}</li>
                @endforelse
            </ul>
        </section>
    </div>

    @foreach ([
        'reject' => [__('Reject this application'), 'changeStatus(\'rejected\')', __('The merchant can no longer use test mode.')],
        'suspend' => [__('Suspend this merchant'), 'changeStatus(\'suspended\')', __('Live keys are revoked and payments stop immediately.')],
        'ban' => [__('Ban this merchant'), 'changeStatus(\'banned\')', __('Every API key is revoked, payments stop in test and live mode, and the team loses access to the portal.')],
        'archive' => [__('Archive this merchant'), 'archive', __('The merchant disappears from the portal and every API key stops working. Records are kept and you can restore it later.')],
    ] as $modal => [$title, $submit, $explanation])
        <flux:modal :name="$modal" class="w-full max-w-md">
            <form wire:submit="{{ $submit }}" class="space-y-5">
                <flux:heading size="lg">{{ $title }}</flux:heading>
                <flux:text>{{ $explanation }}</flux:text>
                <flux:textarea wire:model="reason" :label="__('Reason shown to the merchant')" rows="3" required />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="danger" data-test="confirm-{{ $modal }}">{{ __('Confirm') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endforeach

    <flux:modal name="edit-merchant" class="w-full max-w-2xl">
        <form wire:submit="updateProfile" class="space-y-5">
            <flux:heading size="lg">{{ __('Edit merchant details') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="profile.name" :label="__('Business name')" required />
                <flux:input wire:model="profile.legal_name" :label="__('Legal name')" required />
                <flux:select wire:model="profile.activity" :label="__('Business activity')" required>
                    @foreach (array_unique([...array_values(BusinessActivities::all()), $merchant->activity]) as $label)
                        <flux:select.option :value="$label">{{ __($label) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="profile.commercial_registration" :label="__('Commercial registration number')" dir="ltr" />
                <flux:input wire:model="profile.email" type="email" :label="__('Business email')" dir="ltr" required />
                <flux:input wire:model="profile.phone" :label="__('Phone number')" dir="ltr" required />
                <flux:input wire:model="profile.website" type="url" :label="__('Website')" dir="ltr" class="sm:col-span-2" />
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-merchant">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
