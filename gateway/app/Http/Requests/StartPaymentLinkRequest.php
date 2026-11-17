<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PaymentLink;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

class StartPaymentLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var PaymentLink $link */
        $link = $this->route('link');

        return [
            'name' => ['required', 'string', 'max:80'],
            'phone' => [$link->collect_phone ? 'required' : 'nullable', 'string', 'regex:/^\+?[0-9 ]{8,16}$/'],
            'email' => [$link->collect_email ? 'required' : 'nullable', 'email:rfc', 'max:120'],
            'amount' => [$link->hasFixedAmount() ? 'prohibited' : 'required', 'string', Money::amountRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Enter your name.'),
            'phone.required' => __('Enter your phone number.'),
            'phone.regex' => __('Enter a valid phone number.'),
            'email.required' => __('Enter your email address.'),
            'amount.required' => __('Enter the amount you want to pay.'),
            'amount.regex' => __('Enter the amount in :currency, for example :example', ['currency' => Money::currency(), 'example' => Money::example()]),
        ];
    }

    public function amountInPounds(): ?int
    {
        $amount = $this->validated('amount');

        return $amount === null ? null : Money::toMinorUnits((string) $amount);
    }

    /**
     * @return array{name: string, phone: ?string, email: ?string}
     */
    public function payer(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'phone' => $this->validated('phone'),
            'email' => $this->validated('email'),
        ];
    }
}
