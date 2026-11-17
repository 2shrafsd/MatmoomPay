<?php

use App\Actions\Merchants\UpdateMerchantProfile;
use App\Actions\Merchants\ManageMerchantDocuments;
use App\Data\MerchantProfileData;
use App\Enums\DocumentType;
use App\Exceptions\GatewayException;
use App\Livewire\PortalComponent;
use App\Rules\Iban;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use App\Support\BusinessActivities;

new #[Title('Business profile')] class extends PortalComponent {
    use WithFileUploads;

    public string $documentType = 'commercial_registration';

    public $documentFile = null;
    public string $name = '';
    public string $legal_name = '';
    public string $activity = '';
    public string $commercial_registration = '';
    public string $email = '';
    public string $phone = '';
    public string $website = '';

    public string $bank_name = '';
    public string $account_name = '';
    public string $iban = '';

    public function mount(): void
    {
        $merchant = $this->merchant();

        $this->fill([
            'name' => $merchant->name,
            'legal_name' => $merchant->legal_name,
            'activity' => $merchant->activity,
            'commercial_registration' => (string) $merchant->commercial_registration,
            'email' => $merchant->email,
            'phone' => $merchant->phone,
            'website' => (string) $merchant->website,
            'bank_name' => (string) $merchant->settlement_bank_name,
            'account_name' => (string) $merchant->settlement_account_name,
        ]);
    }

    public function saveProfile(UpdateMerchantProfile $updateMerchantProfile): void
    {
        $this->authorize('update', $this->merchant());

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'legal_name' => ['required', 'string', 'max:160'],
            'activity' => ['required', Rule::in([...array_values(BusinessActivities::all()), $this->merchant()->activity])],
            'commercial_registration' => ['nullable', 'string', 'max:40', 'regex:/^[0-9A-Za-z\-\/]+$/'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ]{8,16}$/'],
            'website' => ['nullable', 'url:https,http', 'max:255'],
        ]);

        $updateMerchantProfile->handle($this->merchant(), MerchantProfileData::fromArray($validated), Auth::user());

        Flux::toast(variant: 'success', text: __('Business profile saved.'));
    }

    public function saveBankAccount(UpdateMerchantProfile $updateMerchantProfile): void
    {
        $this->authorize('update', $this->merchant());

        $this->validate([
            'bank_name' => ['required', 'string', 'max:80'],
            'account_name' => ['required', 'string', 'max:160'],
            'iban' => ['required', 'string', new Iban],
        ]);

        $updateMerchantProfile->updateSettlementAccount($this->merchant(), $this->bank_name, $this->account_name, Iban::normalize($this->iban), Auth::user());

        $this->iban = '';
        Flux::toast(variant: 'success', text: __('Bank account saved. We will verify it before the next payout, and every owner and admin got an email about the change.'));
    }

    public function uploadDocument(ManageMerchantDocuments $documents): void
    {
        $this->authorize('update', $this->merchant());

        $this->validate([
            'documentType' => ['required', Rule::enum(DocumentType::class)],
            'documentFile' => ['required', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(5 * 1024)],
        ]);

        try {
            $documents->upload($this->merchant(), DocumentType::from($this->documentType), $this->documentFile, Auth::user());
        } catch (GatewayException $exception) {
            $this->addError('documentFile', $exception->data['error'][0]['message'] ?? $exception->getMessage());

            return;
        }

        $this->reset('documentFile');
        Flux::toast(variant: 'success', text: __('Document uploaded. Our team will review it.'));
    }

    public function removeDocument(string $documentId, ManageMerchantDocuments $documents): void
    {
        $document = $this->merchant()->documents()->where('public_id', $documentId)->firstOrFail();
        $this->authorize('delete', $document);

        try {
            $documents->remove($document, Auth::user());
        } catch (GatewayException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());
        }
    }

    public function with(): array
    {
        $merchant = $this->merchant();

        return [
            'merchant' => $merchant,
            'canEdit' => Auth::user()->can('update', $merchant),
            'documents' => $merchant->documents()->latest('id')->get(),
            'pricing' => rtrim(rtrim(number_format($merchant->fee_rate_bps / 100, 2), '0'), '.').'% + '.Money::format($merchant->fee_fixed),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-portal.page-header :heading="__('Business profile')" :subheading="__('Details your customers see, and the bank account we pay you into.')" />

    <section class="surface grid gap-4 p-5 text-sm sm:grid-cols-3 sm:p-6">
        <div><p class="text-zinc-500">{{ __('Account status') }}</p><p class="mt-1"><x-portal.status :status="$merchant->status" /></p></div>
        <div><p class="text-zinc-500">{{ __('Pricing') }}</p><p class="ltr-nums mt-1 text-start font-semibold">{{ $pricing }}</p><p class="text-xs text-zinc-500">{{ __('per successful payment') }}</p></div>
        <div><p class="text-zinc-500">{{ __('Merchant ID') }}</p><p class="ltr-nums mt-1 text-start font-mono">{{ $merchant->public_id }}</p></div>
        @if ($merchant->status_reason)
            <div class="sm:col-span-3"><flux:callout icon="information-circle" :heading="__('Note from our team')"><flux:callout.text>{{ $merchant->status_reason }}</flux:callout.text></flux:callout></div>
        @endif
    </section>

    <section class="surface p-5 sm:p-6">
        <flux:heading size="lg">{{ __('Business details') }}</flux:heading>
        <form wire:submit="saveProfile" class="mt-5 grid gap-5 sm:grid-cols-2">
            <flux:input wire:model="name" :label="__('Business name')" :disabled="! $canEdit" required />
            <flux:input wire:model="legal_name" :label="__('Registered company name')" :disabled="! $canEdit" required />
            <flux:select wire:model="activity" :label="__('Business activity')" :disabled="! $canEdit" required>
                @foreach (array_unique([...array_values(\App\Support\BusinessActivities::all()), $merchant->activity]) as $label)
                    <flux:select.option value="{{ $label }}">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="commercial_registration" :label="__('Commercial registration number')" dir="ltr" :disabled="! $canEdit" />
            <flux:input wire:model="email" type="email" :label="__('Business email')" dir="ltr" :disabled="! $canEdit" required />
            <flux:input wire:model="phone" type="tel" :label="__('Phone number')" dir="ltr" :disabled="! $canEdit" required />
            <flux:input wire:model="website" type="url" :label="__('Website')" dir="ltr" :disabled="! $canEdit" class="sm:col-span-2" />
            @if ($canEdit)
                <div class="sm:col-span-2"><flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button></div>
            @endif
        </form>
    </section>

    <section class="surface p-5 sm:p-6">
        <flux:heading size="lg">{{ __('Settlement bank account') }}</flux:heading>
        <flux:text class="mt-1">
            @if ($merchant->settlement_iban)
                {{ __('Current account:') }} <span class="ltr-nums">{{ $merchant->settlement_bank_name }} · {{ $merchant->maskedIban() }}</span>
                @if ($merchant->settlement_account_verified_at)
                    <flux:badge size="sm" color="green" class="ms-1">{{ __('Verified') }}</flux:badge>
                @else
                    <flux:badge size="sm" color="amber" class="ms-1">{{ __('Under review') }}</flux:badge>
                    <span class="mt-1 block">{{ __('Our team checks every new account before the next payout.') }}</span>
                @endif
            @else
                {{ __('No bank account yet. Settlements wait until you add one.') }}
            @endif
        </flux:text>

        @if ($canEdit)
            <form wire:submit="saveBankAccount" class="mt-5 grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="bank_name" :label="__('Bank')" :placeholder="__('For example: Bank of Khartoum')" required />
                <flux:input wire:model="account_name" :label="__('Account holder name')" required />
                <flux:input wire:model="iban" :label="__('IBAN')" placeholder="SD00 0000 0000 0000 00" dir="ltr" autocomplete="off" class="sm:col-span-2" required />
                <div class="sm:col-span-2">
                    <flux:text class="mb-3 text-xs">{{ __('For your protection, changing the bank account is logged and the full IBAN is never shown again after saving.') }}</flux:text>
                    <flux:button type="submit" variant="primary">{{ __('Save bank account') }}</flux:button>
                </div>
            </form>
        @endif
    </section>

    <section class="surface p-5 sm:p-6" aria-labelledby="documents-heading">
        <flux:heading id="documents-heading" size="lg">{{ __('Verification documents') }}</flux:heading>
        <flux:text class="mt-1">{{ __('We need these before live payments open. Files are stored privately and only our review team can open them.') }}</flux:text>

        <ul class="mt-4 grid gap-2 text-sm sm:grid-cols-3">
            @foreach (\App\Enums\DocumentType::required() as $required)
                @php($latest = $documents->where('type', $required)->sortByDesc('id')->first())
                @php($state = $latest?->status)
                <li class="flex items-center gap-2 rounded-xl border border-zinc-200 p-3">
                    <flux:icon
                        :name="match ($state) { \App\Enums\DocumentStatus::Approved => 'check-circle', \App\Enums\DocumentStatus::Rejected => 'x-circle', \App\Enums\DocumentStatus::Pending => 'clock', default => 'document' }"
                        variant="mini"
                        @class([
                            'text-mint-600' => $state === \App\Enums\DocumentStatus::Approved,
                            'text-red-600' => $state === \App\Enums\DocumentStatus::Rejected,
                            'text-gold-700' => $state === \App\Enums\DocumentStatus::Pending,
                            'text-zinc-400' => $state === null,
                        ])
                    />
                    <span class="flex-1">{{ $required->label() }}</span>
                    <span class="text-xs text-zinc-500">{{ $state?->label() ?? __('Not uploaded') }}</span>
                </li>
            @endforeach
        </ul>

        @if ($documents->isNotEmpty())
            <ul class="mt-5 divide-y divide-zinc-200 text-sm">
                @foreach ($documents as $document)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="doc-{{ $document->public_id }}">
                        <div class="min-w-0">
                            <p class="font-medium">{{ $document->type->label() }}</p>
                            <p class="truncate text-zinc-500" dir="ltr">{{ $document->original_name }} · {{ number_format($document->size / 1024) }} KB</p>
                            @if ($document->review_note)
                                <p class="text-red-600">{{ $document->review_note }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="$document->status->color()">{{ $document->status->label() }}</flux:badge>
                            <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('documents.download', $document)" :aria-label="__('Download')" />
                            @if ($canEdit && $document->status !== \App\Enums\DocumentStatus::Approved)
                                <flux:button size="sm" variant="ghost" icon="trash" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Remove this document?')) }}, danger: true, run: () => $wire.removeDocument('{{ $document->public_id }}') })" :aria-label="__('Remove :document', ['document' => $document->type->label()])" />
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($canEdit)
            <form wire:submit="uploadDocument" class="mt-5 grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">
                <flux:select wire:model="documentType" :label="__('Document')">
                    @foreach (\App\Enums\DocumentType::cases() as $type)
                        <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:field>
                    <flux:label for="document-file">{{ __('File (PDF, JPG or PNG, up to 5 MB)') }}</flux:label>
                    <input id="document-file" type="file" wire:model="documentFile" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" aria-describedby="document-file-progress" class="block w-full text-sm file:me-3 file:rounded-full file:border-0 file:bg-mint-100 file:px-4 file:py-2 file:font-medium file:text-ink-900">
                    <p id="document-file-progress" class="text-xs text-zinc-500" wire:loading wire:target="documentFile">{{ __('Uploading the file…') }}</p>
                    <flux:error name="documentFile" />
                </flux:field>
                <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="documentFile,uploadDocument">{{ __('Upload') }}</flux:button>
            </form>
        @endif
    </section>
</div>
