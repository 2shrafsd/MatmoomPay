<?php

declare(strict_types=1);

namespace App\Services\Payments\Processors;

use App\Enums\CardBrand;
use App\Enums\CardType;
use App\Enums\ChallengeType;
use App\Services\Payments\Contracts\PaymentProcessor;
use App\Services\Payments\Data\CardDetails;
use App\Services\Payments\Data\CardToken;
use App\Services\Payments\Data\ChargeOutcome;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\ChargeResult;
use App\Services\Payments\Data\RefundResult;
use App\Services\Payments\Exceptions\CardRejected;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Test-mode processor. Behaves like an acquirer for the published test cards and never touches
 * a real network. The card number is reduced to a scenario name inside an encrypted token.
 */
final class SandboxProcessor implements PaymentProcessor
{
    /**
     * Keyed by card number (PHP stores these numeric keys as integers).
     *
     * @var array<int, array{scenario: string, type: CardType}>
     */
    public const TEST_CARDS = [
        '4242424242424242' => ['scenario' => 'otp_approve', 'type' => CardType::Debit],
        '4000000000000002' => ['scenario' => 'otp_decline', 'type' => CardType::Debit],
        '4456530000001096' => ['scenario' => '3ds_approve', 'type' => CardType::Credit],
        '4456530000001104' => ['scenario' => '3ds_decline', 'type' => CardType::Credit],
        '5123450000000008' => ['scenario' => 'otp_approve', 'type' => CardType::Credit],
    ];

    private const TOKEN_PREFIX = 'tok_sbx_';

    private const CHARGE_CACHE_PREFIX = 'sandbox:charge:';

    private const REFUND_CACHE_PREFIX = 'sandbox:refund:';

    public function name(): string
    {
        return 'sandbox';
    }

    public function tokenize(CardDetails $card): CardToken
    {
        $brand = CardBrand::fromNumber($card->number) ?? throw CardRejected::unsupportedBrand();
        $profile = self::TEST_CARDS[$card->number] ?? ['scenario' => 'unsupported', 'type' => CardType::Debit];

        $payload = json_encode(['s' => $profile['scenario']], JSON_THROW_ON_ERROR);

        return new CardToken(
            token: self::TOKEN_PREFIX.Crypt::encryptString($payload),
            fingerprint: hash_hmac('sha256', $card->number, (string) config('app.key')),
            bin: $card->bin(),
            lastFour: $card->lastFour(),
            brand: $brand,
            type: $profile['type'],
            expiryMonth: $card->expiryMonth,
            expiryYear: $card->expiryYear,
        );
    }

    public function charge(ChargeRequest $request): ChargeResult
    {
        $result = $this->decide($request);

        Cache::put(self::CHARGE_CACHE_PREFIX.$request->reference, [
            'outcome' => $result->outcome->value,
            'txn' => $result->processorReference,
            'challenge' => $result->challengeType?->value,
            'reason' => $result->declineReason,
        ], now()->addDay());

        return $result;
    }

    /**
     * Answers like a bank's transaction inquiry: what happened to the charge we sent with this reference.
     */
    public function retrieve(string $reference): ?ChargeResult
    {
        /** @var array{outcome: string, txn: string, challenge: ?string, reason: ?string}|null $charge */
        $charge = Cache::get(self::CHARGE_CACHE_PREFIX.$reference);

        if ($charge === null) {
            return null;
        }

        return new ChargeResult(
            ChargeOutcome::from($charge['outcome']),
            $charge['txn'],
            $charge['challenge'] !== null ? ChallengeType::from($charge['challenge']) : null,
            $charge['reason'],
        );
    }

    private function decide(ChargeRequest $request): ChargeResult
    {
        $reference = 'sbx_'.Str::random(20);

        return match ($this->scenario($request->token)) {
            'otp_approve' => $request->customerPresent
                ? ChargeResult::challenge($reference, ChallengeType::Otp)
                : ChargeResult::succeeded($reference),
            'otp_decline' => $request->customerPresent
                ? ChargeResult::challenge($reference, ChallengeType::Otp)
                : ChargeResult::declined($reference, 'Declined by the issuing bank.'),
            '3ds_approve', '3ds_decline' => ChargeResult::challenge($reference, ChallengeType::ThreeDSecure),
            default => ChargeResult::declined($reference, 'This card is not supported in test mode. Use one of the test cards.'),
        };
    }

    public function completeChallenge(string $token, string $processorReference, ChallengeType $type, string $code): ChargeResult
    {
        if (! hash_equals((string) config('gateway.sandbox.otp_code'), $code)) {
            return ChargeResult::incorrectCode($processorReference, $type);
        }

        return match ($this->scenario($token)) {
            'otp_approve', '3ds_approve' => ChargeResult::succeeded($processorReference),
            default => ChargeResult::declined($processorReference, 'Declined by the issuing bank.'),
        };
    }

    public function refund(string $processorReference, int $amount, string $currency, string $reference): RefundResult
    {
        return new RefundResult(true, Cache::remember(self::REFUND_CACHE_PREFIX.$reference, now()->addDay(), fn (): string => 'sbx_rf_'.Str::random(20)));
    }

    private function scenario(string $token): string
    {
        if (! str_starts_with($token, self::TOKEN_PREFIX)) {
            return 'unsupported';
        }

        /** @var array{s?: string} $payload */
        $payload = json_decode(Crypt::decryptString(substr($token, strlen(self::TOKEN_PREFIX))), true, flags: JSON_THROW_ON_ERROR);

        return $payload['s'] ?? 'unsupported';
    }
}
