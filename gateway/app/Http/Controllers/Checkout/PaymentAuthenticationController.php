<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checkout;

use App\Actions\Payments\CompletePaymentChallenge;
use App\Enums\PaymentStatus;
use App\Exceptions\GatewayException;
use App\Http\Requests\Checkout\VerifyPaymentRequest;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Hosted verification for off-session payment intents (next_action.url). Reached only through a
 * short-lived signed URL; the payer is sent to the intent's return_url when done.
 */
class PaymentAuthenticationController
{
    public function show(Payment $payment): View|RedirectResponse
    {
        $payment->load(['paymentIntent', 'merchant']);
        $intent = $payment->paymentIntent ?? abort(404);

        if (! $payment->isAwaitingChallenge()) {
            return redirect()->away($intent->return_url);
        }

        return view('pages.checkout.challenge', [
            'session' => null,
            'payment' => $payment,
            'merchant' => $payment->merchant,
            'action' => URL::temporarySignedRoute('authenticate.submit', now()->addMinutes(15), ['payment' => $payment->public_id]),
            'attemptsLeft' => max(0, (int) config('gateway.checkout.max_challenge_attempts') - $payment->challenge_attempts),
        ]);
    }

    public function submit(VerifyPaymentRequest $request, Payment $payment, CompletePaymentChallenge $completeChallenge): RedirectResponse
    {
        $payment->load('paymentIntent');
        $intent = $payment->paymentIntent ?? abort(404);

        try {
            $payment = $completeChallenge->handle($payment, (string) $request->validated('code'));
        } catch (GatewayException) {
            return redirect()->away($intent->return_url);
        }

        if ($payment->status === PaymentStatus::InProcess) {
            return redirect()->to(URL::temporarySignedRoute('authenticate.show', now()->addMinutes(15), ['payment' => $payment->public_id]))
                ->withErrors(['code' => __('That code is not correct. Check the message from your bank and try again.')]);
        }

        return redirect()->away($intent->return_url);
    }
}
