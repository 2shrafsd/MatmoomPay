<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $reference
 * @property string $customer_name
 * @property string $customer_phone
 * @property list<array{product_id: int, name: string, quantity: int, unit_amount: int}> $items
 * @property int $total
 * @property string $status pending, paid, failed, cancelled, expired or abandoned
 * @property string|null $session_id
 * @property string|null $access_token_hash SHA-256 of the token the mobile app uses to read the order.
 * @property string|null $invoice
 * @property Carbon|null $paid_at
 * @property Carbon $created_at
 */
#[Fillable(['reference', 'customer_name', 'customer_phone', 'items', 'total', 'status', 'session_id', 'access_token_hash', 'invoice', 'paid_at'])]
#[Hidden(['access_token_hash'])]
class Order extends Model
{
    /**
     * Phone numbers accepted at checkout, on the web and in the mobile app.
     */
    public const PHONE_PATTERN = '/^\+?[0-9 ]{8,16}$/';

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'total' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * Creates a pending order. Prices come from the products, never from the customer.
     *
     * @param  list<array{product: Product, quantity: int, subtotal: int}>  $lines
     */
    public static function place(string $customerName, string $customerPhone, array $lines): self
    {
        return self::create([
            'reference' => 'MJ-'.Str::upper(Str::random(8)),
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'items' => array_map(fn (array $line) => [
                'product_id' => $line['product']->id,
                'name' => $line['product']->name,
                'quantity' => $line['quantity'],
                'unit_amount' => $line['product']->price,
            ], $lines),
            'total' => array_sum(array_column($lines, 'subtotal')),
            'status' => 'pending',
        ]);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Applies a checkout session read from the gateway with our secret key. The order is paid only when
     * the gateway says so and charged exactly the order total.
     *
     * @param  array<string, mixed>  $session
     */
    public function applyGatewaySession(array $session): void
    {
        if (($session['payment_status'] ?? null) === 'paid' && (int) ($session['total_amount'] ?? -1) === $this->total) {
            $this->update(['status' => 'paid', 'invoice' => $session['invoice'] ?? null, 'paid_at' => now()]);
        }
    }

    /**
     * Gives the mobile app a token to read this order later. Only its hash is stored.
     */
    public function issueAccessToken(): string
    {
        $token = Str::random(40);
        $this->update(['access_token_hash' => hash('sha256', $token)]);

        return $token;
    }

    public function acceptsAccessToken(?string $token): bool
    {
        return $this->access_token_hash !== null
            && is_string($token)
            && $token !== ''
            && hash_equals($this->access_token_hash, hash('sha256', $token));
    }

    /**
     * Orders placed from this browser. Order pages show the customer's name and phone, so only the
     * browser that placed an order may open it.
     */
    public static function placedInSession(): array
    {
        return (array) session('placed_orders', []);
    }

    public function belongsToThisBrowser(): bool
    {
        return in_array($this->reference, self::placedInSession(), true);
    }

    public function rememberInThisBrowser(): void
    {
        session()->push('placed_orders', $this->reference);
        session()->put('pending_order', $this->reference);
    }
}
