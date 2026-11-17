<?php

declare(strict_types=1);

namespace App\Http\Controllers\PaymentLinks;

use App\Actions\PaymentLinks\StartPaymentLinkCheckout;
use App\Exceptions\GatewayException;
use App\Http\Requests\StartPaymentLinkRequest;
use App\Models\CheckoutSession;
use App\Models\PaymentLink;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class PublicPaymentLinkController
{
    public function show(PaymentLink $link): View
    {
        $link->load('merchant');

        return view('pages.links.show', [
            'link' => $link,
            'merchant' => $link->merchant,
            'available' => $link->isAcceptingPayments()
                && $link->merchant->canCollect($link->livemode),
        ]);
    }

    public function start(StartPaymentLinkRequest $request, PaymentLink $link, StartPaymentLinkCheckout $startCheckout): RedirectResponse
    {
        $link->load('merchant');

        try {
            $session = $startCheckout->handle($link, $request->amountInPounds(), $request->payer());
        } catch (GatewayException $exception) {
            $field = $exception->data['error'][0]['field'] ?? 'amount';

            $message = __($exception->data['error'][0]['message'] ?? $exception->getMessage(), ['amount' => Money::format(Money::minimumAmount())]);

            return back()->withErrors([$field => $message])->withInput();
        }

        return redirect()->to(URL::temporarySignedRoute('checkout.show', now()->addHour(), ['session' => $session->public_id]));
    }

    public function thanks(Request $request, PaymentLink $link): View
    {
        $link->load('merchant');

        $session = CheckoutSession::query()
            ->where('payment_link_id', $link->id)
            ->where('client_reference_id', (string) $request->query('reference'))
            ->first();

        return view('pages.links.thanks', ['link' => $link, 'merchant' => $link->merchant, 'session' => $session]);
    }
}
