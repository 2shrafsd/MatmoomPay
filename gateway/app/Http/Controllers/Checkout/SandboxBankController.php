<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checkout;

use App\Enums\CardBrand;
use App\Enums\CardType;
use App\Enums\ChallengeType;
use App\Http\Requests\Checkout\PayWithCardRequest;
use App\Services\Payments\Data\CardToken;
use App\Services\Payments\Data\ChargeOutcome;
use App\Services\Payments\Processors\SandboxHostedProcessor;
use App\Services\Payments\Processors\SandboxProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The simulated bank behind SandboxHostedProcessor. It stands in for an acquirer's payment page in
 * test mode only: it accepts the published test cards and answers with a signed result.
 */
class SandboxBankController
{
    private const MAX_OTP_ATTEMPTS = 3;

    public function __construct(private SandboxProcessor $sandbox) {}

    public function show(Request $request): View
    {
        return view('pages.sandbox-bank.card', ['order' => $this->order($request), 'p' => $request->query('p'), 's' => $request->query('s')]);
    }

    public function card(PayWithCardRequest $request): View
    {
        $order = $this->order($request);
        $token = $this->sandbox->tokenize($request->card());

        $request->session()->put($this->stateKey($order), ['card' => self::cardToArray($token), 'attempts' => 0, 'txn' => 'sbx_'.Str::random(20)]);

        return view('pages.sandbox-bank.otp', ['order' => $order, 'p' => $request->input('p'), 's' => $request->input('s'), 'card' => $token]);
    }

    public function otp(Request $request): View|RedirectResponse
    {
        $order = $this->order($request);
        $state = $request->session()->get($this->stateKey($order)) ?? abort(410);
        $request->validate(['code' => ['required', 'string', 'regex:/^\d{4,8}$/']]);

        $token = self::cardFromArray($state['card']);
        $result = $this->sandbox->completeChallenge($token->token, $state['txn'], ChallengeType::Otp, (string) $request->input('code'));

        if ($result->outcome === ChargeOutcome::IncorrectChallengeCode && ++$state['attempts'] < self::MAX_OTP_ATTEMPTS) {
            $request->session()->put($this->stateKey($order), $state);

            return view('pages.sandbox-bank.otp', ['order' => $order, 'p' => $request->input('p'), 's' => $request->input('s'), 'card' => $token])
                ->withErrors(['code' => __('That code is not correct. Check the message from your bank and try again.')]);
        }

        $request->session()->forget($this->stateKey($order));
        $approved = $result->outcome === ChargeOutcome::Succeeded;

        return redirect()->away($this->resultUrl($order, $approved, $state['txn'], $token, $approved ? null : ($result->declineReason ?? 'Too many incorrect verification codes.')));
    }

    public function cancel(Request $request): RedirectResponse
    {
        return redirect()->away($this->order($request)['cancel']);
    }

    /**
     * The signed order sent by SandboxHostedProcessor. Anything unsigned or expired is refused.
     *
     * @return array{ref: string, amount: int, currency: string, description: string, return: string, cancel: string, locale: string, exp: int}
     */
    private function order(Request $request): array
    {
        abort_unless(config('gateway.sandbox.flow') === 'hosted', 404);

        $payload = (string) $request->input('p', '');

        abort_unless($payload !== '' && hash_equals(SandboxHostedProcessor::sign($payload), (string) $request->input('s', '')), 400);

        $order = SandboxHostedProcessor::decode($payload);

        abort_unless(is_array($order) && isset($order['ref'], $order['amount'], $order['return'], $order['exp']), 400);
        abort_if((int) $order['exp'] < now()->getTimestamp(), 410);

        /** @var array{ref: string, amount: int, currency: string, description: string, return: string, cancel: string, locale: string, exp: int} $order */
        return $order;
    }

    /**
     * @param  array{ref: string, amount: int, currency: string, return: string}  $order
     */
    private function resultUrl(array $order, bool $approved, string $txn, CardToken $card, ?string $reason): string
    {
        $result = SandboxHostedProcessor::encode([
            'ref' => $order['ref'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'approved' => $approved,
            'txn' => $txn,
            'reason' => $reason,
            'ts' => now()->getTimestamp(),
            'card' => self::cardToArray($card),
        ]);

        return $order['return'].(str_contains($order['return'], '?') ? '&' : '?').http_build_query(['r' => $result, 's' => SandboxHostedProcessor::sign($result)]);
    }

    /**
     * @return array{token: string, fingerprint: string, bin: string, last4: string, brand: string, type: string, exp_month: int, exp_year: int}
     */
    private static function cardToArray(CardToken $card): array
    {
        return [
            'token' => $card->token,
            'fingerprint' => $card->fingerprint,
            'bin' => $card->bin,
            'last4' => $card->lastFour,
            'brand' => $card->brand->value,
            'type' => $card->type->value,
            'exp_month' => $card->expiryMonth,
            'exp_year' => $card->expiryYear,
        ];
    }

    /**
     * @param  array{token: string, fingerprint: string, bin: string, last4: string, brand: string, type: string, exp_month: int, exp_year: int}  $card
     */
    private static function cardFromArray(array $card): CardToken
    {
        return new CardToken($card['token'], $card['fingerprint'], $card['bin'], $card['last4'], CardBrand::from($card['brand']), CardType::from($card['type']), $card['exp_month'], $card['exp_year']);
    }

    /**
     * @param  array{ref: string}  $order
     */
    private function stateKey(array $order): string
    {
        return 'sandbox_bank.'.$order['ref'];
    }
}
