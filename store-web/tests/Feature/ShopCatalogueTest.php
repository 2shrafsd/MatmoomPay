<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_the_first_page_shows_eight_products_and_a_link_to_the_rest(): void
    {
        $this->get(route('shop.index'))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->count() === 8)
            ->assertViewHas('moreUrl', fn ($url) => str_contains($url, 'page=2'));

        $this->get(route('shop.index', ['page' => 2]))
            ->assertViewHas('products', fn ($products) => $products->count() === Product::count())
            ->assertViewHas('moreUrl', null);
    }

    public function test_a_category_shows_only_its_products(): void
    {
        $this->get(route('shop.index', ['category' => 'earbuds']))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('slug')->all() === ['earbuds-silver', 'earbuds-case-white']);
    }

    public function test_products_can_be_sorted_by_price_and_filtered_by_a_price_limit(): void
    {
        $this->get(route('shop.index', ['sort' => 'price_asc', 'page' => 2]))
            ->assertViewHas('products', fn ($products) => $products->pluck('price')->all() === Product::orderBy('price')->pluck('price')->all());

        $this->get(route('shop.index', ['sort' => 'price_desc', 'max' => 150_000]))
            ->assertViewHas('products', fn ($products) => $products->first()->slug === 'earbuds-silver' && $products->max('price') <= 150_000);
    }

    public function test_best_sellers_are_ranked_by_units_sold_in_paid_orders(): void
    {
        $speaker = Product::where('slug', 'speaker-capsule-black')->firstOrFail();
        $line = ['product_id' => $speaker->id, 'name' => $speaker->name, 'quantity' => 5, 'unit_amount' => $speaker->price];

        Order::create(['reference' => 'MJ-BEST0001', 'customer_name' => 'نور', 'customer_phone' => '+249912345678', 'items' => [$line], 'total' => 380_000, 'status' => 'paid']);
        Order::create(['reference' => 'MJ-BEST0002', 'customer_name' => 'نور', 'customer_phone' => '+249912345678', 'items' => [[...$line, 'product_id' => 1, 'quantity' => 9]], 'total' => 1, 'status' => 'failed']);

        $this->get(route('shop.index', ['category' => 'popular']))
            ->assertViewHas('products', fn ($products) => $products->first()->is($speaker) && $products->count() === 8);
    }
}
