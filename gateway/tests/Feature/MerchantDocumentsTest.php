<?php

use App\Enums\DocumentStatus;
use App\Enums\MerchantRole;
use App\Models\Merchant;
use App\Models\MerchantDocument;
use App\Models\User;
use App\Support\GatewayAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(MerchantDocument::disk());
    $this->owner = User::factory()->create();
    $this->merchant = Merchant::factory()->pending()->withMember($this->owner, MerchantRole::Owner)->create();
    $this->actingAs($this->owner)->withSession(['auth.password_confirmed_at' => time()]);
    app()->instance(GatewayAccount::class, new GatewayAccount($this->merchant, false));
});

function pdfFile(string $name, int $kilobytes = 20): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%âãÏÓ\n1 0 obj << /Type /Catalog >> endobj\n".str_repeat('0', $kilobytes * 1024)."\n%%EOF");
}

function uploadAs(UploadedFile $file, string $type = 'commercial_registration')
{
    return Livewire::test('pages::portal.business')
        ->set('documentType', $type)
        ->set('documentFile', $file)
        ->call('uploadDocument');
}

test('the owner uploads a pdf that is stored privately under a random name', function () {
    uploadAs(pdfFile('CR certificate.pdf'))->assertHasNoErrors();

    $document = MerchantDocument::sole();
    expect($document->path)->toStartWith('kyc/'.$this->merchant->public_id.'/')
        ->not->toContain('CR certificate')
        ->and($document->status)->toBe(DocumentStatus::Pending);

    Storage::disk(MerchantDocument::disk())->assertExists($document->path);
});

test('executable or oversized files are refused', function (UploadedFile $file) {
    uploadAs($file)->assertHasErrors('documentFile');

    expect(MerchantDocument::count())->toBe(0);
})->with([
    'php disguised as pdf' => fn () => UploadedFile::fake()->createWithContent('shell.pdf', '<?php system($_GET["c"]);'),
    'svg image' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg onload="alert(1)"></svg>'),
    'larger than 5 MB' => fn () => pdfFile('big.pdf', 6000),
]);

test('documents download as attachments for the owner only', function () {
    uploadAs(UploadedFile::fake()->image('id.png'), 'owner_id');
    $document = MerchantDocument::sole();

    $this->get(route('documents.download', $document))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename='.$document->downloadName());

    $outsider = User::factory()->create();
    Merchant::factory()->withMember($outsider)->create();
    $this->actingAs($outsider)->get(route('documents.download', $document))->assertNotFound();

    $viewer = User::factory()->create();
    $this->merchant->users()->attach($viewer->id, ['role' => MerchantRole::Viewer->value]);
    $this->actingAs($viewer)->get(route('documents.download', $document))->assertNotFound();
});

test('an admin accepts and rejects documents and accepted ones cannot be removed', function () {
    uploadAs(pdfFile('cr.pdf'));
    uploadAs(pdfFile('bank.pdf'), 'bank_letter');
    [$bank, $cr] = MerchantDocument::orderByDesc('id')->get()->all();

    $admin = User::factory()->withTwoFactor()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();
    $this->actingAs($admin);

    Livewire::test('pages::admin.merchants.show', ['merchant' => $this->merchant])
        ->call('reviewDocument', $cr->public_id, 'approve')
        ->call('reviewDocument', $bank->public_id, 'reject')
        ->assertHasErrors('documentNote')
        ->set('documentNote', 'The IBAN on the letter does not match')
        ->call('reviewDocument', $bank->public_id, 'reject');

    expect($cr->refresh()->status)->toBe(DocumentStatus::Approved)
        ->and($bank->refresh()->status)->toBe(DocumentStatus::Rejected)
        ->and($bank->review_note)->toBe('The IBAN on the letter does not match');

    $this->actingAs($this->owner);
    Livewire::test('pages::portal.business')->call('removeDocument', $cr->public_id);
    expect(MerchantDocument::whereKey($cr->id)->exists())->toBeTrue();
});
