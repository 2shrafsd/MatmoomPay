<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\Product;
use App\Support\Sdg;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order as the mobile app sees it. The customer's name and phone are left out.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array{reference: string, status: string, total: int, total_formatted: string, items: list<array{name: string, quantity: int, unit_amount: int, image_url: string|null}>, invoice: string|null, paid_at: string|null, created_at: string}
     */
    public function toArray(Request $request): array
    {
        $products = Product::whereKey(array_column($this->items, 'product_id'))->get()->keyBy('id');

        return [
            'reference' => $this->reference,
            'status' => $this->status,
            'total' => $this->total,
            'total_formatted' => Sdg::format($this->total),
            'items' => array_map(fn (array $item) => [
                'name' => $item['name'],
                'quantity' => (int) $item['quantity'],
                'unit_amount' => (int) $item['unit_amount'],
                'image_url' => $products->get($item['product_id'])?->imageUrl(),
            ], $this->items),
            'invoice' => $this->invoice,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
