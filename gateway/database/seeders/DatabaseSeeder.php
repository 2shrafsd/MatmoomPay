<?php

namespace Database\Seeders;

use App\Actions\ApiKeys\CreateApiKey;
use App\Actions\Checkout\CreateCheckoutSession;
use App\Actions\Payments\CreatePendingPayment;
use App\Actions\Payments\FinalizeSuccessfulPayment;
use App\Data\CheckoutSessionData;
use App\Enums\ApiKeyType;
use App\Enums\MerchantRole;
use App\Enums\MerchantStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\PaymentLink;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\Data\CardDetails;
use App\Services\Payments\Processors\SandboxProcessor;
use App\Support\GatewayAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

/**
 * Demo data for local development only. Every person and business here is fictional.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('The demo seeder never runs in production.');

            return;
        }

        $admin = User::factory()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@matmoompay.test',
        ]);
        $admin->forceFill(['is_platform_admin' => true])->save();

        $owner = User::factory()->create([
            'name' => 'Muna Abdelrahim',
            'email' => 'merchant@matmoompay.test',
        ]);

        $merchant = Merchant::factory()->withMember($owner, MerchantRole::Owner)->create([
            'name' => 'Nile Aroma',
            'legal_name' => 'Nile Aroma Trading Co. Ltd',
            'activity' => 'Online store',
            'email' => 'hello@nilearoma.test',
            'website' => 'https://nilearoma.test',
            'status' => MerchantStatus::Active,
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);

        Merchant::factory()->pending()->count(2)->create();

        $createApiKey = app(CreateApiKey::class);
        $createApiKey->handle($merchant, false, ApiKeyType::Publishable, 'Default publishable key', $owner);
        [, $secret] = $createApiKey->handle($merchant, false, ApiKeyType::Secret, 'Local development', $owner);

        $this->seedPayments(new GatewayAccount($merchant, false));

        PaymentLink::factory()->for($merchant)->create(['title' => 'Sudanese perfume gift box', 'amount' => 18_500, 'collect_phone' => true, 'created_by' => $owner->id]);
        PaymentLink::factory()->for($merchant)->openAmount()->create(['title' => 'Custom order deposit', 'created_by' => $owner->id]);

        $plan = Plan::factory()->for($merchant)->create(['name' => 'Jebena coffee club', 'amount' => 9_500]);
        Subscription::factory()->for($plan)->create([
            'merchant_id' => $merchant->id,
            'customer_id' => Customer::query()->where('merchant_id', $merchant->id)->value('id'),
            'current_period_start' => now()->subDays(10),
            'current_period_end' => now()->addDays(20),
        ]);

        $this->command->info('Admin: admin@matmoompay.test / password. Two-factor setup is required on the first visit to /admin.');
        $this->command->info('Merchant: merchant@matmoompay.test / password');
        $this->command->info("Test secret key: {$secret}");
    }

    private function seedPayments(GatewayAccount $account): void
    {
        $products = [
            ['name' => 'Sudanese bakhoor, 250 g', 'quantity' => 2, 'unit_amount' => 6_500],
            ['name' => 'Karkade (hibiscus), 1 kg', 'quantity' => 1, 'unit_amount' => 3_200],
            ['name' => 'Clay mabkhara incense burner', 'quantity' => 1, 'unit_amount' => 14_750],
            ['name' => 'Gum arabic, 500 g', 'quantity' => 3, 'unit_amount' => 4_900],
            ['name' => 'Barakawi dates gift box', 'quantity' => 2, 'unit_amount' => 8_250],
        ];

        $customer = Customer::factory()->for($account->merchant)->create(['client_customer_id' => 'mohamed@example.sd']);
        $sandbox = app(SandboxProcessor::class);

        $base = now();

        foreach (range(1, 24) as $i) {
            Date::setTestNow($base->subDays(random_int(0, 13))->subMinutes(random_int(0, 600)));

            $session = app(CreateCheckoutSession::class)->handle($account, new CheckoutSessionData(
                clientReferenceId: 'order-'.(1000 + $i),
                products: [$products[$i % count($products)]],
                successUrl: 'https://nilearoma.test/thanks',
                cancelUrl: 'https://nilearoma.test/cart',
                metadata: ['Customer name' => ['Amna Osman', 'Mohamed Elamin', 'Hiba Ibrahim', 'Ahmed Elfadil'][$i % 4]],
                customer: $i % 5 === 0 ? $customer : null,
            ));

            $number = $i % 6 === 0 ? '4000000000000002' : '4242424242424242';
            $token = $sandbox->tokenize(new CardDetails($number, 12, (int) now()->addYears(2)->format('Y'), '123', 'Demo'));
            $payment = app(CreatePendingPayment::class)->handle($account->merchant, false, $session->total_amount, $token, 'sandbox', ['checkout_session_id' => $session->id, 'customer_id' => $session->customer_id]);

            if ($i % 6 === 0) {
                $payment->fill(['status' => PaymentStatus::Failed, 'failure_reason' => 'Declined by the issuing bank.', 'card_token' => null, 'completed_at' => now()])->save();
            } else {
                $payment->fill(['processor_reference' => 'sbx_seed_'.$i])->save();
                app(FinalizeSuccessfulPayment::class)->handle($payment);
            }
        }

        Date::setTestNow();
    }
}
