<?php

use App\Enums\WebhookEvent;
use App\Actions\Webhooks\QueueWebhook;
use App\Actions\Webhooks\ResendWebhookDelivery;
use App\Actions\Webhooks\SaveWebhookEndpoint;
use App\Livewire\PortalComponent;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\OutboundUrlGuard;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\WithPagination;

new #[Title('Webhooks')] class extends PortalComponent {
    use WithPagination;

    public string $url = '';

    public bool $active = true;

    public bool $showSecret = false;

    public ?string $viewingPayload = null;

    public function mount(): void
    {
        if ($endpoint = $this->endpoint) {
            $this->url = $endpoint->url;
            $this->active = $endpoint->is_active;
        }
    }

    public function save(SaveWebhookEndpoint $saveWebhookEndpoint): void
    {
        $this->authorize('manageDevelopers', $this->merchant());

        $this->validate(['url' => ['required', 'string', 'max:500', 'url:http,https'], 'active' => ['boolean']]);

        if ($error = OutboundUrlGuard::storageError($this->url, $this->livemode())) {
            $this->addError('url', $error);

            return;
        }

        $saveWebhookEndpoint->handle($this->account(), $this->url, $this->active, Auth::user());
        unset($this->endpoint);

        Flux::toast(variant: 'success', text: __('Webhook settings saved.'));
    }

    public function rotateSecret(SaveWebhookEndpoint $saveWebhookEndpoint): void
    {
        $this->authorize('manageDevelopers', $this->merchant());

        $endpoint = $this->endpoint ?? abort(404);
        $saveWebhookEndpoint->rotateSecret($endpoint, Auth::user());
        unset($this->endpoint);
        $this->showSecret = true;

        Flux::toast(variant: 'warning', text: __('New secret created. Update your server before the next event arrives.'));
    }

    /**
     * Sends a signed sample event so developers can check their endpoint and signature code end to end.
     */
    public function sendTest(QueueWebhook $queueWebhook): void
    {
        $this->authorize('manageDevelopers', $this->merchant());

        $delivery = $queueWebhook->handle($this->merchant(), $this->livemode(), WebhookEvent::WebhookTest, [
            'message' => 'This is a test event from '.config('app.name').'. Answer with any 2xx status.',
            'merchant_id' => $this->merchant()->public_id,
        ]);

        if ($delivery === null) {
            Flux::toast(variant: 'warning', text: __('Save an active endpoint first.'));

            return;
        }

        unset($this->deliveries);
        Flux::toast(variant: 'success', text: __('A test event is on its way. Its result appears in the deliveries below.'));
    }

    public function toggleSecret(): void
    {
        $this->authorize('manageDevelopers', $this->merchant());
        $this->showSecret = ! $this->showSecret;
    }

    public function resend(string $deliveryId, ResendWebhookDelivery $resendWebhookDelivery): void
    {
        $delivery = WebhookDelivery::query()->forAccount($this->merchant(), $this->livemode())->where('public_id', $deliveryId)->firstOrFail();
        $this->authorize('resend', $delivery);

        $resendWebhookDelivery->handle($delivery);
        Flux::toast(text: __('The event was queued for delivery.'));
    }

    public function viewPayload(string $deliveryId): void
    {
        $delivery = WebhookDelivery::query()->forAccount($this->merchant(), $this->livemode())->where('public_id', $deliveryId)->firstOrFail();
        $this->authorize('view', $delivery);

        $this->viewingPayload = json_encode(json_decode($delivery->payload, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Flux::modal('payload')->show();
    }

    #[Computed]
    public function endpoint(): ?WebhookEndpoint
    {
        return WebhookEndpoint::query()->forAccount($this->merchant(), $this->livemode())->first();
    }

    /**
     * @return LengthAwarePaginator<int, WebhookDelivery>
     */
    #[Computed]
    public function deliveries(): LengthAwarePaginator
    {
        return WebhookDelivery::query()->forAccount($this->merchant(), $this->livemode())->latest('id')->paginate(15);
    }

    public function with(): array
    {
        return ['canManage' => $this->role()->canManageDevelopers()];
    }
}; ?>

<div>
    <x-portal.page-header :heading="__('Webhooks')" :subheading="__('We send a signed POST request to your URL whenever a checkout, payment or refund changes.')" />

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,28rem)]">
        <section class="surface p-5 sm:p-6">
            <form wire:submit="save" class="space-y-5">
                <flux:input wire:model="url" type="url" :label="__('Endpoint URL')" placeholder="https://example.com/webhooks/matmoom" dir="ltr" :disabled="! $canManage" required />
                <flux:switch wire:model="active" :label="__('Send events to this URL')" :disabled="! $canManage" />
                @if ($canManage)
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                @endif
            </form>

            @if ($this->endpoint)
                <div class="mt-6 border-t border-zinc-200 pt-6">
                    <flux:label>{{ __('Signing secret') }}</flux:label>
                    @if ($showSecret && $canManage)
                        <x-portal.copy :value="$this->endpoint->secret" class="mt-2" />
                    @else
                        <code class="mt-2 block rounded-lg bg-zinc-50 px-3 py-2 font-mono text-sm" dir="ltr">whsec_••••••••••••••••</code>
                    @endif
                    @if ($canManage)
                        <div class="mt-3 flex flex-wrap gap-2">
                            <flux:button size="sm" wire:click="toggleSecret" :icon="$showSecret ? 'eye-slash' : 'eye'">{{ $showSecret ? __('Hide') : __('Reveal') }}</flux:button>
                            <flux:button size="sm" wire:click="sendTest" icon="paper-airplane" data-test="send-test-event">{{ __('Send a test event') }}</flux:button>
                            <flux:button size="sm" variant="ghost" x-on:click="$dispatch('confirm-action', { message: {{ \Illuminate\Support\Js::from(__('Create a new secret? Signatures made with the old one will stop matching on your server.')) }}, danger: true, run: () => $wire.rotateSecret() })" icon="arrow-path">{{ __('Roll secret') }}</flux:button>
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <section class="surface p-5 sm:p-6">
            <flux:heading>{{ __('Verify every request') }}</flux:heading>
            <flux:text class="mt-2 text-sm">{{ __('Compute an HMAC-SHA256 of the raw body, a dash and the timestamp header, using your signing secret. Compare it with the signature header.') }}</flux:text>
            <pre class="mt-4 overflow-x-auto rounded-xl bg-zinc-950 p-4 text-xs leading-relaxed text-zinc-100" dir="ltr"><code>$body = file_get_contents('php://input');
$timestamp = $_SERVER['HTTP_MATMOOM_TIMESTAMP'];
$expected = hash_hmac('sha256', $body.'-'.$timestamp, $secret);

if (! hash_equals($expected, $_SERVER['HTTP_MATMOOM_SIGNATURE'])
    || abs(time() - (int) $timestamp) > 300) {
    http_response_code(400);
    exit;
}</code></pre>
        </section>
    </div>

    <section class="surface mt-6" aria-labelledby="deliveries-heading">
        <div class="p-5 pb-2"><flux:heading id="deliveries-heading">{{ __('Recent events') }}</flux:heading></div>
        @if ($this->deliveries->isEmpty())
            <x-portal.empty icon="signal" :heading="__('No events sent yet')" :body="__('Events appear here once you have an endpoint and a checkout or payment changes.')" />
        @else
            <div class="data-table">
                <flux:table :paginate="$this->deliveries">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Event') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Attempts') }}</flux:table.column>
                        <flux:table.column class="max-md:hidden">{{ __('Response') }}</flux:table.column>
                        <flux:table.column class="max-sm:hidden">{{ __('Date') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->deliveries as $delivery)
                            <flux:table.row :key="$delivery->public_id">
                                <flux:table.cell variant="strong"><code class="font-mono text-xs" dir="ltr">{{ $delivery->event_type }}</code></flux:table.cell>
                                <flux:table.cell><x-portal.status :status="$delivery->status" /></flux:table.cell>
                                <flux:table.cell class="max-md:hidden">{{ $delivery->attempts }}</flux:table.cell>
                                <flux:table.cell class="max-md:hidden">
                                    <span class="ltr-nums">{{ $delivery->response_status ?? '—' }}</span>
                                    @if ($delivery->last_error)
                                        <flux:tooltip :content="$delivery->last_error"><button type="button" class="inline align-middle"><flux:icon.exclamation-circle variant="micro" class="inline text-red-500" /><span class="sr-only">{{ __('Error: :message', ['message' => $delivery->last_error]) }}</span></button></flux:tooltip>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="max-sm:hidden">{{ $delivery->created_at->locale(app()->getLocale())->translatedFormat('j M · H:i:s') }}</flux:table.cell>
                                <flux:table.cell class="text-end whitespace-nowrap">
                                    <flux:button size="sm" variant="ghost" wire:click="viewPayload('{{ $delivery->public_id }}')">{{ __('View') }}</flux:button>
                                    @if ($canManage)
                                        <flux:button size="sm" variant="ghost" wire:click="resend('{{ $delivery->public_id }}')">{{ __('Resend') }}</flux:button>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    </section>

    <flux:modal name="payload" class="w-full max-w-2xl">
        <flux:heading size="lg">{{ __('Event payload') }}</flux:heading>
        <pre class="mt-4 max-h-[60vh] overflow-auto rounded-xl bg-zinc-950 p-4 text-xs text-zinc-100" dir="ltr"><code>{{ $viewingPayload }}</code></pre>
    </flux:modal>
</div>
