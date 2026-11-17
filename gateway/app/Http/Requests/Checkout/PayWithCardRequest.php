<?php

declare(strict_types=1);

namespace App\Http\Requests\Checkout;

use App\Rules\LuhnCardNumber;
use App\Services\Payments\Data\CardDetails;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class PayWithCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'card_number' => preg_replace('/\D/', '', (string) $this->input('card_number')),
            'expiry' => preg_replace('/\s/', '', (string) $this->input('expiry')),
            'cvc' => preg_replace('/\D/', '', (string) $this->input('cvc')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'card_number' => ['required', 'string', new LuhnCardNumber],
            'expiry' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/', $this->notExpired(...)],
            'cvc' => ['required', 'string', 'regex:/^\d{3,4}$/'],
            'cardholder_name' => ['required', 'string', 'max:60'],
            'save_card' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'card_number.required' => __('Enter your card number.'),
            'expiry.required' => __('Enter the expiry date as MM/YY.'),
            'expiry.regex' => __('Enter the expiry date as MM/YY.'),
            'cvc.required' => __('Enter the 3 or 4 digit security code.'),
            'cvc.regex' => __('Enter the 3 or 4 digit security code.'),
            'cardholder_name.required' => __('Enter the name shown on the card.'),
        ];
    }

    private function notExpired(string $attribute, mixed $value, Closure $fail): void
    {
        [$month, $year] = $this->expiryParts((string) $value);

        if ($month === 0 || now()->startOfMonth()->greaterThan(now()->setDate(2000 + $year, $month, 1)->startOfMonth())) {
            $fail(__('This card has expired.'));
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function expiryParts(string $expiry): array
    {
        if (! preg_match('/^(\d{2})\/(\d{2})$/', $expiry, $matches)) {
            return [0, 0];
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    public function card(): CardDetails
    {
        [$month, $year] = $this->expiryParts((string) $this->validated('expiry'));

        return new CardDetails(
            number: (string) $this->validated('card_number'),
            expiryMonth: $month,
            expiryYear: 2000 + $year,
            cvc: (string) $this->validated('cvc'),
            holderName: (string) $this->validated('cardholder_name'),
        );
    }
}
