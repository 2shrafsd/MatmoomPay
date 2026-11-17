<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checkout;

use App\Actions\Checkout\CompleteHostedCheckout;
use App\Actions\Checkout\PayCheckoutWithCard;
use App\Actions\Checkout\PayCheckoutWithSavedCard;
use App\Actions\Checkout\StartHostedCheckout;
use App\Actions\Payments\CompletePaymentChallenge;
use App\Enums\ApiKeyType;
use App\Enums\CheckoutPaymentStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\GatewayException;
use App\Http\Requests\Checkout\PayWithCardRequest;
use App\Http\Requests\Checkout\VerifyPaymentRequest;
use App\Models\ApiKey;
use App\Models\CheckoutSession;
use App\Models\HostedPaymentAttempt;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Payments\Exceptions\InvalidProcessorCallback;
use App\Services\Payments\PaymentProcessorResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * The hosted payment page at /pay/{session_id}?key={publishable_key}.
 *
 * Opening the page with a valid publishable key (or a signed link) grants this browser session access;
 * every later step checks that grant, so a leaked session id alone cannot be used to pay or verify.
 */
class HostedCheckoutController
{
    public function show(Request $request, CheckoutSession $session): View|RedirectResponse
    {
        if (! $this->grantedBy($request, $session)) {
            abort(404);
        }

        $request->session()->put($this->accessKey($session), true);
        $session->load(['merchant', 'customer', 'plan']);

        if (! $session->canTakePayment()) {
            return $this->statusPage($session);
        }

        return view('pages.checkout.show', [
            'session' => $session,
            'savedCards' => $this->savedCards($session),
            'canSaveCard' => $session->customer_id !== null,
            'hosted' => app(PaymentProcessorResolver::class)->hostedFor($session->livemode) !== null,
        ]);
    }

    /**
     * Sends the cardholder to the bank's own payment page.
     */
    public function startHosted(Request $request, CheckoutSession $session, StartHostedCheckout $startHostedCheckout, PaymentProcessorResolver $processors): RedirectResponse|Response
    {
        $this->ensureAccess($request, $session);
        $processor = $processors->hostedFor($session->livemode) ?? abort(404);

        try {
            $redirect = $startHostedCheckout->handle($session, $request->boolean('save_card'), $processor);
        } catch (GatewayException $exception) {
            return back()->withErrors(['payment' => $this->translate($exception->getMessage())]);
        }

        if ($redirect->method === 'POST') {
            return response()->view('pages.checkout.redirect', ['redirect' => $redirect]);
        }

        return redirect()->away($redirect->url);
    }

    /**
     * Where the bank sends the cardholder back. Authenticated by the bank's signature, not by the browser session.
     */
    public function hostedReturn(Request $request, HostedPaymentAttempt $attempt, CompleteHostedCheckout $completeHostedCheckout, PaymentProcessorResolver $processors): RedirectResponse
    {
        $processor = $processors->hostedFor($attempt->livemode) ?? abort(404);
        $session = $attempt->checkoutSession;

        try {
            $payment = $completeHostedCheckout->handle($attempt, $processor->completeHostedPayment($request));
        } catch (InvalidProcessorCallback $exception) {
            Log::warning('Rejected a hosted payment callback.', ['attempt' => $attempt->public_id, 'reason' => $exception->getMessage(), 'ip' => $request->ip()]);

            abort(400);
        }

        $request->session()->put($this->accessKey($session), true);

        if ($payment === null) {
            return redirect()->route('checkout.show', $session)->withErrors(['payment' => __('The payment did not go through: :reason', ['reason' => __('Declined by the issuing bank.')])]);
        }

        return $this->afterAttempt($request, $session, $payment);
    }

    public function pay(PayWithCardRequest $request, CheckoutSession $session, PayCheckoutWithCard $payWithCard): RedirectResponse
    {
        $this->ensureAccess($request, $session);

        try {
            $payment = $payWithCard->handle($session, $request->card(), $request->boolean('save_card'), $request->ip());
        } catch (GatewayException $exception) {
            return back()->withErrors(['payment' => $this->translate($exception->getMessage())])->withInput($request->only('cardholder_name'));
        }

        return $this->afterAttempt($request, $session, $payment);
    }

    public function payWithSavedCard(Request $request, CheckoutSession $session, PaymentMethod $card, PayCheckoutWithSavedCard $payWithSavedCard): RedirectResponse
    {
        $this->ensureAccess($request, $session);

        try {
            $payment = $payWithSavedCard->handle($session, $card, $request->ip());
        } catch (GatewayException $exception) {
            return back()->withErrors(['payment' => $this->translate($exception->getMessage())]);
        }

        return $this->afterAttempt($request, $session, $payment);
    }

    public function challenge(Request $request, CheckoutSession $session, Payment $payment): View|RedirectResponse
    {
        $this->ensurePaymentAccess($request, $session, $payment);

        if (! $payment->isAwaitingChallenge()) {
            return redirect()->route('checkout.show', $session);
        }

        $session->load('merchant');

        return view('pages.checkout.challenge', [
            'session' => $session,
            'payment' => $payment,
            'merchant' => $session->merchant,
            'action' => route('checkout.verify.submit', [$session, $payment]),
            'attemptsLeft' => max(0, (int) config('gateway.checkout.max_challenge_attempts') - $payment->challenge_attempts),
        ]);
    }

    public function verify(VerifyPaymentRequest $request, CheckoutSession $session, Payment $payment, CompletePaymentChallenge $completeChallenge): RedirectResponse
    {
        $this->ensurePaymentAccess($request, $session, $payment);

        try {
            $payment = $completeChallenge->handle($payment, (string) $request->validated('code'));
        } catch (GatewayException) {
            return redirect()->route('checkout.show', $session);
        }

        if ($payment->status === PaymentStatus::InProcess) {
            return back()->withErrors(['code' => __('That code is not correct. Check the message from your bank and try again.')]);
        }

        return $this->afterAttempt($request, $session, $payment);
    }

    public function cancel(Request $request, CheckoutSession $session): RedirectResponse
    {
        $this->ensureAccess($request, $session);

        return redirect()->away($session->cancel_url);
    }

    private function afterAttempt(Request $request, CheckoutSession $session, Payment $payment): RedirectResponse
    {
        if ($payment->status === PaymentStatus::Successful) {
            $request->session()->forget($this->accessKey($session));

            return redirect()->away($session->success_url);
        }

        if ($payment->status === PaymentStatus::Failed) {
            return redirect()->route('checkout.show', $session)
                ->withErrors(['payment' => __('The payment did not go through: :reason', ['reason' => $this->translate((string) $payment->failure_reason)])]);
        }

        $request->session()->put($this->paymentKey($payment), true);

        return redirect()->route('checkout.verify', [$session, $payment]);
    }

    private function statusPage(CheckoutSession $session): View
    {
        $payment = $session->payment_status === CheckoutPaymentStatus::Paid
            ? $session->payments()->where('status', PaymentStatus::Successful)->oldest('completed_at')->first()
            : null;

        return view('pages.checkout.status', ['session' => $session, 'merchant' => $session->merchant, 'payment' => $payment]);
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    private function savedCards(CheckoutSession $session): Collection
    {
        if ($session->customer === null) {
            return collect();
        }

        return $session->customer->paymentMethods()->latest('id')->get()->reject(fn (PaymentMethod $card) => $card->isExpired())->values();
    }

    private function grantedBy(Request $request, CheckoutSession $session): bool
    {
        if ($request->hasValidSignature()) {
            return true;
        }

        $key = (string) $request->query('key', '');

        if ($key === '') {
            return (bool) $request->session()->get($this->accessKey($session));
        }

        return ApiKey::query()
            ->where('token_hash', ApiKey::hashToken($key))
            ->where('type', ApiKeyType::Publishable)
            ->where('merchant_id', $session->merchant_id)
            ->where('livemode', $session->livemode)
            ->whereNull('revoked_at')
            ->exists();
    }

    private function ensureAccess(Request $request, CheckoutSession $session): void
    {
        if (! $request->session()->get($this->accessKey($session))) {
            abort(404);
        }
    }

    private function ensurePaymentAccess(Request $request, CheckoutSession $session, Payment $payment): void
    {
        $this->ensureAccess($request, $session);

        if ($payment->checkout_session_id !== $session->id || ! $request->session()->get($this->paymentKey($payment))) {
            abort(404);
        }
    }

    private function accessKey(CheckoutSession $session): string
    {
        return 'checkout_access.'.$session->public_id;
    }

    private function paymentKey(Payment $payment): string
    {
        return 'checkout_payment.'.$payment->public_id;
    }

    private function translate(string $message): string
    {
        return __($message);
    }
}
