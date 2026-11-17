import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:matjar_app/features/products/data/models/product.dart';

/// Local sort orders. The API lists newest first, so [newest] keeps its order.
enum ProductSort { newest, priceLowToHigh, priceHighToLow }

/// Category, search text and sort chosen on the products screen.
@immutable
class ProductsQuery {
  const ProductsQuery({
    this.category,
    this.search = '',
    this.sort = ProductSort.newest,
  });

  /// Null means «الكل».
  final String? category;
  final String search;
  final ProductSort sort;

  ProductsQuery copyWith({
    String? category,
    bool clearCategory = false,
    String? search,
    ProductSort? sort,
  }) => ProductsQuery(
    category: clearCategory ? null : category ?? this.category,
    search: search ?? this.search,
    sort: sort ?? this.sort,
  );
}

final productsQueryProvider =
    NotifierProvider<ProductsQueryNotifier, ProductsQuery>(
      ProductsQueryNotifier.new,
    );

class ProductsQueryNotifier extends Notifier<ProductsQuery> {
  @override
  ProductsQuery build() => const ProductsQuery();

  void selectCategory(String? category) => state = category == null
      ? state.copyWith(clearCategory: true)
      : state.copyWith(category: category);

  void setSearch(String search) => state = state.copyWith(search: search);

  void setSort(ProductSort sort) => state = state.copyWith(sort: sort);
}

/// Distinct categories in the order they first appear.
List<String> categoriesOf(List<Product> products) => [
  ...{for (final product in products) ?product.category},
];

/// Filters and sorts [products] for display. A category that no longer
/// exists after a refresh falls back to all products. Sorting is stable, so
/// equal prices keep the server's order.
List<Product> applyProductsQuery(List<Product> products, ProductsQuery query) {
  final category = query.category;
  final hasCategory =
      category != null && categoriesOf(products).contains(category);
  final needle = query.search.trim().toLowerCase();

  final indexed = [
    for (final (index, product) in products.indexed)
      if ((!hasCategory || product.category == category) &&
          (needle.isEmpty || product.name.toLowerCase().contains(needle)))
        (index, product),
  ];

  int byPrice((int, Product) a, (int, Product) b, {required bool ascending}) {
    final compared = ascending
        ? a.$2.price.compareTo(b.$2.price)
        : b.$2.price.compareTo(a.$2.price);
    return compared != 0 ? compared : a.$1.compareTo(b.$1);
  }

  switch (query.sort) {
    case ProductSort.newest:
      break;
    case ProductSort.priceLowToHigh:
      indexed.sort((a, b) => byPrice(a, b, ascending: true));
    case ProductSort.priceHighToLow:
      indexed.sort((a, b) => byPrice(a, b, ascending: false));
  }
  return [for (final (_, product) in indexed) product];
}
