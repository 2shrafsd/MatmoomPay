<?php

use App\Enums\MerchantRole;
use App\Models\CheckoutSession;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->merchant = Merchant::factory()->withMember($this->user, MerchantRole::Viewer)->create();
});

test('the export only contains the merchant own test payments', function () {
    $mine = Payment::factory()->for($this->merchant)->create();
    $live = Payment::factory()->for($this->merchant)->create(['livemode' => true]);
    $foreign = Payment::factory()->create();

    $csv = $this->actingAs($this->user)->get(route('portal.payments.export'))->assertOk()->streamedContent();

    expect($csv)->toContain($mine->public_id)
        ->not->toContain($live->public_id)
        ->not->toContain($foreign->public_id);
});

test('the export applies the status filter', function () {
    $ok = Payment::factory()->for($this->merchant)->create();
    $failed = Payment::factory()->for($this->merchant)->failed()->create();

    $csv = $this->actingAs($this->user)->get(route('portal.payments.export', ['status' => 'Failed']))->streamedContent();

    expect($csv)->toContain($failed->public_id)->not->toContain($ok->public_id);
});

test('spreadsheet formulas in merchant references are neutralised', function () {
    $session = CheckoutSession::factory()->for($this->merchant)->create(['client_reference_id' => '=HYPERLINK("https://evil.example","x")']);
    Payment::factory()->for($this->merchant)->create(['checkout_session_id' => $session->id]);

    $csv = $this->actingAs($this->user)->get(route('portal.payments.export'))->streamedContent();

    expect($csv)->toContain("'=HYPERLINK")->not->toContain(',=HYPERLINK');
});

test('guests cannot export', function () {
    $this->get(route('portal.payments.export'))->assertRedirect(route('login'));
});
