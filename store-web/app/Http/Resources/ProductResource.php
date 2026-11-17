<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Support\Sdg;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array{slug: string, name: string, description: string, price: int, price_formatted: string, image_url: string|null, color: string, category: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (int) $this->price,
            'price_formatted' => Sdg::format((int) $this->price),
            'image_url' => $this->imageUrl(),
            'color' => $this->color,
            'category' => $this->category,
        ];
    }
}
