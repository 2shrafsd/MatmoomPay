<?php

use App\Enums\DocumentStatus;
use App\Enums\MerchantRole;
use App\Models\Merchant;
use App\Models\MerchantDocument;
use App\Models\User;
use App\Support\GatewayAccount;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('every string the app translates has an Arabic translation', function () {
    $arabic = json_decode(file_get_contents(lang_path('ar.json')), true);
    $missing = [];

    $files = new AppendIterator;
    $files->append(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))));
    $files->append(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Notifications'))));
    $files->append(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Enums'))));
    foreach ($files as $file) {
        if (! str_ends_with($file->getFilename(), '.php') || str_contains($file->getPathname(), '/vendor/')) {
            continue;
        }

        preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)+)'/", file_get_contents($file->getPathname()), $matches);

        foreach ($matches[1] as $key) {
            $key = stripslashes($key);

            if (! array_key_exists($key, $arabic)) {
                $missing[] = $key;
            }
        }
    }

    expect(array_values(array_unique($missing)))->toBe([]);
});

test('arabic validation messages cover file uploads', function () {
    app()->setLocale('ar');

    expect(__('validation.uploaded', ['attribute' => __('validation.attributes.documentFile')]))->toContain('الملف')
        ->and(__('validation.max.file', ['attribute' => 'x', 'max' => 5]))->not->toBe('validation.max.file');
});

test('a user receives emails in the language they chose', function () {
    Notification::fake();
    $user = User::factory()->create(['locale' => 'ar']);

    $user->notify(new ResetPassword('token'));

    Notification::assertSentTo($user, ResetPassword::class, fn ($notification, $channels, $notifiable, $locale) => $locale === 'ar');
});

test('the activity is stored in English and shown translated', function () {
    app()->setLocale('ar');
    $merchant = Merchant::factory()->create(['activity' => 'Retail']);

    expect($merchant->activityLabel())->toBe('تجزئة');
});

test('a rejected document is shown as rejected, not as done', function () {
    $owner = User::factory()->create();
    $merchant = Merchant::factory()->pending()->withMember($owner, MerchantRole::Owner)->create();
    MerchantDocument::create([
        'merchant_id' => $merchant->id,
        'type' => 'commercial_registration',
        'original_name' => 'cr.pdf',
        'path' => 'kyc/cr.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1024,
        'sha256' => str_repeat('a', 64),
    ])->forceFill(['status' => DocumentStatus::Rejected])->save();

    $this->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()]);
    app()->instance(GatewayAccount::class, new GatewayAccount($merchant, false));

    Livewire::test('pages::portal.business')
        ->assertSee(__('Rejected'))
        ->assertDontSeeHtml('name="check-circle"');
});

test('a pending merchant sees what is left before live payments open', function () {
    $owner = User::factory()->create();
    Merchant::factory()->pending()->withMember($owner, MerchantRole::Owner)->create();

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="activation-steps"', false)
        ->assertSee(__('Add the bank account for payouts'));
});

test('a malformed request gets a branded, translated error page', function () {
    config(['gateway.sandbox.flow' => 'hosted']);
    app()->setLocale('ar');

    $this->get('/sandbox-bank?p=x&s=y')->assertStatus(400)->assertSee(__('Something in this request was not right'));
});
