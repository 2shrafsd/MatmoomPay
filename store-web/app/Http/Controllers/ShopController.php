<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ShopController extends Controller
{
    /**
     * Products shown per «عرض المزيد» step.
     */
    public const PAGE_SIZE = 8;

    /**
     * How many products the «الأكثر طلبًا» tab lists.
     */
    public const BEST_SELLERS = 8;

    /**
     * @var array<string, string>
     */
    public const SORTS = [
        'newest' => 'الأحدث',
        'price_asc' => 'السعر من الأقل',
        'price_desc' => 'السعر من الأعلى',
    ];

    /**
     * @var list<int>
     */
    public const PRICE_LIMITS = [50_000, 150_000, 300_000];

    public function index(Request $request): View
    {
        $category = (string) $request->query('category', '');
        $category = $category === 'popular' || array_key_exists($category, Product::CATEGORIES) ? $category : '';
        $sort = array_key_exists((string) $request->query('sort'), self::SORTS) ? (string) $request->query('sort') : '';
        $maxPrice = in_array((int) $request->query('max'), self::PRICE_LIMITS, true) ? (int) $request->query('max') : null;
        $search = trim(mb_substr((string) $request->query('q', ''), 0, 60));
        $page = max(1, (int) $request->query('page', 1));

        $products = Product::query()
            ->when(isset(Product::CATEGORIES[$category]), fn ($query) => $query->where('category', $category))
            ->when($maxPrice, fn ($query) => $query->where('price', '<=', $maxPrice))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('id')
            ->get();

        if ($category === 'popular') {
            $products = $this->bestSellersAmong($products);
        }

        $products = match ($sort) {
            'newest' => $products->sortByDesc('id'),
            'price_asc' => $products->sortBy('price'),
            'price_desc' => $products->sortByDesc('price'),
            default => $products,
        };

        $shown = $page * self::PAGE_SIZE;

        return view('shop.index', [
            'products' => $products->take($shown)->values(),
            'total' => $products->count(),
            'moreUrl' => $products->count() > $shown ? $request->fullUrlWithQuery(['page' => $page + 1]) : null,
            'tabs' => ['' => 'الكل', 'popular' => 'الأكثر طلبًا'] + Product::CATEGORIES,
            'category' => $category,
            'sort' => $sort,
            'maxPrice' => $maxPrice,
            'search' => $search,
        ]);
    }

    /**
     * Orders products by units sold in paid orders, best first, and keeps the top ones.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    private function bestSellersAmong(Collection $products): Collection
    {
        $unitsSold = [];

        foreach (Order::where('status', 'paid')->pluck('items') as $items) {
            foreach ((array) $items as $item) {
                $unitsSold[$item['product_id']] = ($unitsSold[$item['product_id']] ?? 0) + (int) $item['quantity'];
            }
        }

        return $products
            ->sortBy([fn (Product $a, Product $b) => ($unitsSold[$b->id] ?? 0) <=> ($unitsSold[$a->id] ?? 0), ['id', 'asc']])
            ->take(self::BEST_SELLERS)
            ->values();
    }
}
