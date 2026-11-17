import 'package:flutter_test/flutter_test.dart';
import 'package:matjar_app/features/products/data/models/product.dart';
import 'package:matjar_app/features/products/presentation/view_models/products_query.dart';

import '../../helpers/fixtures.dart';

Product withCategory(Product p, String? category, {int? price, String? slug}) =>
    Product(
      slug: slug ?? p.slug,
      name: p.name,
      description: p.description,
      price: price ?? p.price,
      priceFormatted: p.priceFormatted,
      imageUrl: p.imageUrl,
      colorHex: p.colorHex,
      category: category,
    );

void main() {
  final headphones = withCategory(studioHeadphones, 'headphones');
  final travelHeadphones = withCategory(
    studioHeadphones,
    'headphones',
    slug: 'travel-headphones',
    price: 30000,
  );
  final earbuds = withCategory(proEarbuds, 'earbuds');
  final speaker = withCategory(miniSpeaker, 'speakers');
  final catalogue = [headphones, earbuds, travelHeadphones, speaker];

  test('Product.fromJson reads an optional category and ignores blanks', () {
    expect(
      Product.fromJson({...productJson(), 'category': ' headphones '}).category,
      'headphones',
    );
    expect(
      Product.fromJson({...productJson(), 'category': ''}).category,
      isNull,
    );
    expect(Product.fromJson(productJson()).category, isNull);
  });

  test('categoriesOf lists distinct categories in first-seen order', () {
    expect(categoriesOf(catalogue), ['headphones', 'earbuds', 'speakers']);
    expect(categoriesOf(const [studioHeadphones, proEarbuds]), isEmpty);
  });

  test('newest keeps the server order; a category filters', () {
    expect(applyProductsQuery(catalogue, const ProductsQuery()), catalogue);
    expect(
      applyProductsQuery(
        catalogue,
        const ProductsQuery(category: 'headphones'),
      ),
      [headphones, travelHeadphones],
    );
  });

  test('a category missing after refresh falls back to all products', () {
    expect(
      applyProductsQuery(catalogue, const ProductsQuery(category: 'cables')),
      catalogue,
    );
  });

  test('price sorts are stable for equal prices', () {
    final sameAsEarbuds = withCategory(
      studioHeadphones,
      null,
      price: proEarbuds.price,
    );
    final list = [speaker, earbuds, sameAsEarbuds];
    expect(
      applyProductsQuery(
        list,
        const ProductsQuery(sort: ProductSort.priceLowToHigh),
      ),
      [earbuds, sameAsEarbuds, speaker],
    );
    expect(
      applyProductsQuery(
        list,
        const ProductsQuery(sort: ProductSort.priceHighToLow),
      ),
      [speaker, earbuds, sameAsEarbuds],
    );
  });

  test('search matches part of the name, case-insensitively', () {
    const english = Product(
      slug: 'usb-mic',
      name: 'USB Desk Microphone',
      description: '',
      price: 5000,
      priceFormatted: '',
      imageUrl: null,
      colorHex: '',
    );
    expect(
      applyProductsQuery([
        ...catalogue,
        english,
      ], const ProductsQuery(search: 'micro')),
      [english],
    );
    expect(applyProductsQuery(catalogue, const ProductsQuery(search: 'مكبر')), [
      speaker,
    ]);
  });
}
