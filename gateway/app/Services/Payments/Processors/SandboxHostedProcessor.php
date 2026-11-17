<?php

declare(strict_types=1);

namespace App\Services\Payments\Processors;

use App\Enums\CardBrand;
use App\Enums\CardType;
use App\Enums\ChallengeType;
use App\Services\Payments\Contracts\HostedPaymentPageProcessor;
use App\Services\Payments\Data\CardDetails;
use App\Services\Payments\Data\CardToken;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Data\HostedPaymentRedirect;
use App\Services\Payments\Data\HostedPaymentRequest;
use App\Services\Payments\Data\HostedPaymentResult;
use App\Services\Payments\Data\RefundResult;
use App\Services\Payments\Exceptions\InvalidProcessorCallback;
use Illuminate\Http\Request;

/**
 * Test-mode acquirer with its own payment page (the "simulated bank" under /sandbox-bank). It behaves
 * like a real hosted-page integration: signed redirect out, signed result back, nothing trusted
 * from the browser without a valid signature. Saved-card charges and refunds reuse SandboxProcessor.
 */
final readonly class SandboxHostedProcessor implements HostedPaymentPageProcessor
{
    /**
     * Seconds a signed result stays valid; older callbacks are treated as replays.
     */
    public const RESULT_TTL = 900;

    public function __construct(private SandboxProcessor $sandbox) {}

    public function name(): string
    {
        return 'sandbox';
    }

    public function startHostedPayment(HostedPaymentRequest $request): HostedPaymentRedirect
    {
        $payload = self::encode([
            'ref' => $request->reference,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'description' => $request->description,
            'return' => $request->returnUrl,
            'cancel' => $request->cancelUrl,
            'locale' => $request->locale,
            'exp' => now()->addMinutes(30)->getTimestamp(),
        ]);

        return new HostedPaymentRedirect(route('sandbox-bank.show', ['p' => $payload, 's' => self::sign($payload)]));
    }

    public function completeHostedPayment(Request $request): HostedPaymentResult
    {
        $payload = (string) $request->input('r', '');

        if ($payload === '' || ! hash_equals(self::sign($payload), (string) $request->input('s', ''))) {
            throw new InvalidProcessorCallback('The bank signature is not valid.');
        }

        $data = self::decode($payload);

        if (! is_array($data) || ! isset($data['ref'], $data['amount'], $data['currency'], $data['approved'], $data['txn'], $data['ts'])) {
            throw new InvalidProcessorCallback('The bank answer is incomplete.');
        }

        if (abs(now()->getTimestamp() - (int) $data['ts']) > self::RESULT_TTL) {
            throw new InvalidProcessorCallback('The bank answer has expired.');
        }

        $card = isset($data['card']) && is_array($data['card']) ? new CardToken(
            token: (string) $data['card']['token'],
            fingerprint: (string) $data['card']['fingerprint'],
            bin: (string) $data['card']['bin'],
            lastFour: (string) $data['card']['last4'],
            brand: CardBrand::from((string) $data['card']['brand']),
            type: CardType::from((string) $data['card']['type']),
            expiryMonth: (int) $data['card']['exp_month'],
            expiryYear: (int) $data['card']['exp_year'],
        ) : null;

        return new HostedPaymentResult(
            reference: (string) $data['ref'],
            amount: (int) $data['amount'],
            currency: (string) $data['currency'],
            approved: (bool) $data['approved'],
            processorReference: (string) $data['txn'],
            card: $card,
            declineReason: isset($data['reason']) ? (string) $data['reason'] : null,
        );
    }

    public function tokenize(CardDetails $card): CardToken
    {
        return $this->sandbox->tokenize($card);
    }

    public function charge(ChargeRequest $request): ChargeResult
    {
        return $this->sandbox->charge($request);
    }

    public function completeChallenge(string $token, string $processorReference, ChallengeType $type, string $code): ChargeResult
    {
        return $this->sandbox->completeChallenge($token, $processorReference, $type, $code);
    }

    public function refund(string $processorReference, int $amount, string $currency, string $reference): RefundResult
    {
        return $this->sandbox->refund($processorReference, $amount, $currency, $reference);
    }

    public function retrieve(string $reference): ?ChargeResult
    {
        return $this->sandbox->retrieve($reference);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function encode(array $data): string
    {
        return rtrim(strtr(base64_encode(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    }

    public static function decode(string $payload): mixed
    {
        $json = base64_decode(strtr($payload, '-_', '+/'), true);

        return $json === false ? null : json_decode($json, true);
    }

    public static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, hash_hmac('sha256', 'sandbox-bank', (string) config('app.key')));
    }
}
