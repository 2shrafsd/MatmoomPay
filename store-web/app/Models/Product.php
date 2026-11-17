<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string|null $category One of the keys of self::CATEGORIES.
 * @property string $name
 * @property string $description
 * @property int $price Price in whole Sudanese pounds (no decimals).
 * @property string $color Shown behind the product when it has no photo.
 * @property string|null $image Path under public/, e.g. images/products/headphones-beige.jpg.
 */
#[Fillable(['slug', 'category', 'name', 'description', 'price', 'color', 'image'])]
class Product extends Model
{
    /**
     * Shop categories and their labels, in the order the shop shows them.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'headphones' => 'سماعات رأس',
        'earbuds' => 'سماعات أذن',
        'speakers' => 'مكبرات صوت',
        'accessories' => 'إكسسوارات',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Absolute URL of the product photo, or null when the product has none or the file is missing.
     */
    public function imageUrl(): ?string
    {
        if (! $this->image || ! is_file(public_path($this->image))) {
            return null;
        }

        return asset($this->image);
    }
}
