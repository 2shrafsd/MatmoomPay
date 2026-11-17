import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/error/failure.dart';
import 'package:matjar_app/core/error/result.dart';
import 'package:matjar_app/features/products/data/models/product.dart';
import 'package:matjar_app/features/products/data/products_repository.dart';
import 'package:matjar_app/features/products/presentation/pages/products_page.dart';
import 'package:matjar_app/features/products/presentation/widgets/product_grid_skeleton.dart';
import 'package:matjar_app/features/products/presentation/widgets/product_tile.dart';

import '../../helpers/fixtures.dart';
import '../../helpers/pump_app.dart';

class FakeProductsRepository implements ProductsRepository {
  FakeProductsRepository(this.responses);

  /// One response per call; the last one repeats.
  final List<Future<Result<List<Product>>> Function()> responses;
  int calls = 0;

  @override
  Future<Result<List<Product>>> fetchProducts() {
    final next =
        responses[calls < responses.length ? calls : responses.length - 1];
    calls++;
    return next();
  }
}

Product categorised(Product p, String category) => Product(
  slug: p.slug,
  name: p.name,
  description: p.description,
  price: p.price,
  priceFormatted: p.priceFormatted,
  imageUrl: p.imageUrl,
  colorHex: p.colorHex,
  category: category,
);

void main() {
  Future<FakeProductsRepository> pumpProducts(
    WidgetTester tester,
    List<Future<Result<List<Product>>> Function()> responses, {
    Size size = const Size(390, 2600),
  }) async {
    tester.view.physicalSize = size * 3;
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    final repository = FakeProductsRepository(responses);
    await pumpApp(
      tester,
      const ProductsPage(),
      overrides: [productsRepositoryProvider.overrideWithValue(repository)],
    );
    return repository;
  }

  testWidgets('shows the skeleton while products load', (tester) async {
    final pending = Completer<Result<List<Product>>>();
    await pumpProducts(tester, [() => pending.future]);
    await tester.pump();

    expect(find.byType(SliverProductGridSkeleton), findsOneWidget);
    expect(find.byType(ProductTile), findsNothing);
    expect(find.text('الكل'), findsOneWidget);

    pending.complete(const Success([studioHeadphones]));
    await tester.pumpAndSettle();
    expect(find.byType(SliverProductGridSkeleton), findsNothing);
  });

  testWidgets('shows the error with a retry that reloads', (tester) async {
    final repository = await pumpProducts(tester, [
      () async => const Err(NetworkFailure('offline')),
      () async => const Success([studioHeadphones, proEarbuds]),
    ]);
    await tester.pumpAndSettle();

    expect(find.textContaining('تعذّر الاتصال بالمتجر'), findsOneWidget);
    await tester.tap(find.text('حاول مرة أخرى'));
    await tester.pumpAndSettle();

    expect(repository.calls, 2);
    expect(find.byType(ProductTile), findsNWidgets(2));
  });

  testWidgets('shows the empty state when the shop has no products', (
    tester,
  ) async {
    await pumpProducts(tester, [() async => const Success(<Product>[])]);
    await tester.pumpAndSettle();
    expect(find.text('لا توجد منتجات حاليًا'), findsOneWidget);
  });

  testWidgets(
    'lists products with name and price, and + fills the cart badge',
    (tester) async {
      await pumpProducts(tester, [
        () async => const Success([studioHeadphones, proEarbuds, miniSpeaker]),
      ]);
      await tester.pumpAndSettle();

      expect(find.text('تصفّح السماعات'), findsOneWidget, reason: 'hero CTA');
      expect(find.text('المزايا'), findsOneWidget);
      expect(find.text('كل المنتجات'), findsOneWidget);
      expect(find.byTooltip('الترتيب'), findsOneWidget);
      expect(find.text('سماعة رأس استوديو'), findsOneWidget);
      expect(find.text('12,000 ج.س'), findsOneWidget);
      // Descriptions live in the details sheet, not the grid.
      expect(find.text(studioHeadphones.description), findsNothing);

      final addButtons = find.byTooltip('أضف للسلة');
      expect(addButtons, findsNWidgets(3));
      expect(tester.getSize(addButtons.first).height, greaterThanOrEqualTo(48));

      await tester.tap(addButtons.first);
      await tester.tap(addButtons.first);
      await tester.pump();
      expect(find.text('2'), findsOneWidget, reason: 'badge count');
      expect(find.text('أضفنا سماعة رأس استوديو إلى السلة'), findsOneWidget);
    },
  );

  testWidgets('tapping a tile opens details with the description', (
    tester,
  ) async {
    await pumpProducts(tester, [
      () async => const Success([studioHeadphones]),
    ]);
    await tester.pumpAndSettle();

    await tester.tap(find.text('سماعة رأس استوديو'));
    await tester.pumpAndSettle();

    expect(find.text(studioHeadphones.description), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'أضف للسلة'), findsOneWidget);
  });

  testWidgets('category tabs filter the grid; sort sheet reorders', (
    tester,
  ) async {
    await pumpProducts(tester, [
      () async => Success([
        categorised(studioHeadphones, 'headphones'),
        categorised(proEarbuds, 'earbuds'),
        categorised(miniSpeaker, 'speakers'),
      ]),
    ]);
    await tester.pumpAndSettle();

    await tester.tap(find.text('سماعات أذن'));
    await tester.pumpAndSettle();
    expect(find.byType(ProductTile), findsOneWidget);
    expect(find.text('سماعات أذن برو'), findsOneWidget);
    // The section title follows the chosen category.
    expect(find.text('سماعات أذن'), findsNWidgets(2));

    await tester.tap(find.text('الكل'));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('الترتيب'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('السعر من الأعلى'));
    await tester.pumpAndSettle();

    final names = tester
        .widgetList<ProductTile>(find.byType(ProductTile))
        .map((tile) => tile.product.slug)
        .toList();
    expect(names, ['mini-speaker', 'studio-headphones', 'pro-earbuds']);
  });

  testWidgets('the hero call to action shows headphones', (tester) async {
    await pumpProducts(tester, [
      () async => Success([
        categorised(proEarbuds, 'earbuds'),
        categorised(studioHeadphones, 'headphones'),
        categorised(miniSpeaker, 'gadgets'),
      ]),
    ]);
    await tester.pumpAndSettle();

    // Unknown categories are shown as sent.
    expect(find.text('gadgets'), findsOneWidget);

    await tester.tap(find.text('تصفّح السماعات'));
    await tester.pumpAndSettle();
    final slugs = tester
        .widgetList<ProductTile>(find.byType(ProductTile))
        .map((tile) => tile.product.slug);
    expect(slugs, ['studio-headphones']);
  });

  testWidgets('no overflow on a small phone at 2x text size', (tester) async {
    tester.platformDispatcher.textScaleFactorTestValue = 2;
    addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
    await pumpProducts(tester, [
      () async => const Success([studioHeadphones, proEarbuds, miniSpeaker]),
    ], size: const Size(360, 640));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });
}
