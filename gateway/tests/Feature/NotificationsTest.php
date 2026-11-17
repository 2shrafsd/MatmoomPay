<?php

use App\Actions\Merchants\ManageMerchantDocuments;
use App\Actions\Payments\FinalizeSuccessfulPayment;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\MerchantRole;
use App\Enums\MerchantStatus;
use App\Models\CheckoutSession;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\User;
use App\Notifications\DocumentRejected;
use App\Notifications\MerchantStatusChanged;
use App\Notifications\PaymentReceipt;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->withTwoFactor()->create();
    $this->admin->forceFill(['is_platform_admin' => true])->save();
});

test('owners and admins are emailed when the merchant is suspended, viewers are not', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $merchant = Merchant::factory()->withMember($owner, MerchantRole::Owner)->create();
    $merchant->users()->attach($viewer->id, ['role' => MerchantRole::Viewer->value]);

    $this->actingAs($this->admin);
    Livewire::test('pages::admin.merchants.show', ['merchant' => $merchant])
        ->set('reason', 'Unusual refund activity')
        ->call('changeStatus', 'suspended');

    Notification::assertSentTo($owner, MerchantStatusChanged::class, fn ($notification) => $notification->status === MerchantStatus::Suspended);
    Notification::assertNotSentTo($viewer, MerchantStatusChanged::class);
});

test('the status email renders for every merchant status', function (MerchantStatus $status) {
    $merchant = Merchant::factory()->create(['name' => 'Nile Aroma']);

    $html = (new MerchantStatusChanged($merchant, $status, 'Selling counterfeit goods'))
        ->toMail(User::factory()->create())
        ->render()
        ->toHtml();

    expect($html)->toContain('Selling counterfeit goods');
})->with(MerchantStatus::cases());

test('a rejected document notifies the merchant with the reason', function () {
    $owner = User::factory()->create();
    $merchant = Merchant::factory()->pending()->withMember($owner, MerchantRole::Owner)->create();
    $document = $merchant->documents()->create([
        'type' => DocumentType::BankLetter, 'original_name' => 'bank.pdf', 'path' => 'kyc/x.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'sha256' => str_repeat('0', 64),
    ]);

    app(ManageMerchantDocuments::class)->review($document, DocumentStatus::Rejected, 'The letter is not stamped', $this->admin);

    Notification::assertSentTo($owner, DocumentRejected::class);
    expect((new DocumentRejected($document->refresh()))->toMail($owner)->render()->toHtml())->toContain('The letter is not stamped');
});

test('a payment link payer who left an email receives a receipt', function () {
    Queue::fake();
    $merchant = Merchant::factory()->create(['name' => 'Bait <b>Al</b> Luban']);
    $link = PaymentLink::factory()->for($merchant)->create(['collect_email' => true]);

    $this->post(route('links.start', $link), ['name' => 'Aisha', 'email' => 'aisha@example.om'])->assertRedirect();
    $session = CheckoutSession::sole();
    $payment = Payment::factory()->for($merchant)->create(['status' => 'InProcess', 'checkout_session_id' => $session->id, 'amount' => $session->total_amount]);

    app(FinalizeSuccessfulPayment::class)->handle($payment);

    Notification::assertSentTo(new AnonymousNotifiable, PaymentReceipt::class, function (PaymentReceipt $notification, array $channels, AnonymousNotifiable $notifiable) {
        $html = $notification->toMail($notifiable)->render()->toHtml();

        return $notifiable->routes['mail'] === 'aisha@example.om'
            && str_contains($html, '&lt;b&gt;Al&lt;/b&gt;')
            && ! str_contains($html, '<b>Al</b>');
    });
});
