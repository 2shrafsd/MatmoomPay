// Renders the main screens with real fonts at a phone frame. Regenerate with:
//   flutter test --update-goldens test/goldens
// Goldens depend on the host's font rasteriser; they were recorded on macOS.
@Tags(['golden'])
library;

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/di/core_providers.dart';
import 'package:matjar_app/core/error/result.dart';
import 'package:matjar_app/core/storage/key_value_store.dart';
import 'package:matjar_app/core/theme/app_theme.dart';
import 'package:matjar_app/features/cart/data/models/cart_line.dart';
import 'package:matjar_app/features/cart/presentation/pages/cart_page.dart';
import 'package:matjar_app/features/cart/presentation/view_models/cart_notifier.dart';
import 'package:matjar_app/features/orders/data/models/created_order.dart';
import 'package:matjar_app/features/orders/data/models/order_details.dart';
import 'package:matjar_app/features/orders/data/models/order_status.dart';
import 'package:matjar_app/features/orders/data/models/saved_order.dart';
import 'package:matjar_app/features/orders/data/orders_repository.dart';
import 'package:matjar_app/features/orders/data/saved_orders_store.dart';
import 'package:matjar_app/features/orders/presentation/pages/order_result_page.dart';
import 'package:matjar_app/features/orders/presentation/view_models/order_result_view_model.dart';
import 'package:matjar_app/features/products/data/models/product.dart';
import 'package:matjar_app/features/products/data/products_repository.dart';
import 'package:matjar_app/features/products/presentation/pages/products_page.dart';
import 'package:matjar_app/l10n/generated/app_localizations.dart';

import '../helpers/fixtures.dart';
import '../helpers/load_app_fonts.dart';

const Size frame = Size(390, 844);

class _Products implements ProductsRepository {
  const _Products();

  static Product _in(Product p, String category) => Product(
    slug: p.slug,
    name: p.name,
    description: p.description,
    price: p.price,
    priceFormatted: p.priceFormatted,
    imageUrl: null,
    colorHex: p.colorHex,
    category: category,
  );

  @override
  Future<Result<List<Product>>> fetchProducts() async => Success([
    _in(studioHeadphones, 'headphones'),
    _in(proEarbuds, 'earbuds'),
    _in(miniSpeaker, 'speakers'),
  ]);
}

class _Orders implements OrdersRepository {
  const _Orders();

  @override
  Future<Result<OrderDetails>> fetchOrder({
    required String reference,
    required String accessToken,
  }) async => Success(orderDetails(OrderStatus.paid));

  @override
  Future<Result<CreatedOrder>> createOrder({
    required String customerName,
    required String customerPhone,
    required List<CartLine> lines,
  }) => throw UnimplementedError();
}

class _FilledCart extends CartNotifier {
  @override
  CartState build() => const CartState(
    lines: [
      CartLine(product: studioHeadphones, quantity: 1),
      CartLine(product: proEarbuds, quantity: 2),
    ],
  );
}

Future<void> _pump(
  WidgetTester tester,
  Widget screen, {
  ThemeMode mode = ThemeMode.light,
  List<Override> overrides = const [],
}) async {
  tester.view.physicalSize = frame * 3;
  tester.view.devicePixelRatio = 3;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        productsRepositoryProvider.overrideWithValue(const _Products()),
        ordersRepositoryProvider.overrideWithValue(const _Orders()),
        ...overrides,
      ],
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        darkTheme: AppTheme.dark(),
        themeMode: mode,
        locale: const Locale('ar'),
        supportedLocales: AppLocalizations.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          ...GlobalMaterialLocalizations.delegates,
        ],
        home: screen,
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  setUpAll(loadAppFonts);

  testWidgets('products, light', (tester) async {
    await _pump(tester, const ProductsPage());
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('products_light.png'),
    );
  });

  testWidgets('products grid, dark', (tester) async {
    await _pump(tester, const ProductsPage(), mode: ThemeMode.dark);
    await tester.drag(find.byType(CustomScrollView), const Offset(0, -900));
    await tester.pumpAndSettle();
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('products_grid_dark.png'),
    );
  });

  testWidgets('cart, light', (tester) async {
    await _pump(
      tester,
      const CartPage(),
      overrides: [cartProvider.overrideWith(_FilledCart.new)],
    );
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('cart_light.png'),
    );
  });

  testWidgets('order paid, light', (tester) async {
    final store = InMemoryKeyValueStore();
    await SavedOrdersStore(store).save(
      SavedOrder(
        reference: 'MJ-24A7Q',
        accessToken: 'tok',
        createdAt: DateTime.utc(2026, 11, 17),
      ),
    );
    await _pump(
      tester,
      const OrderResultPage(
        args: (reference: 'MJ-24A7Q', origin: PaymentOrigin.returnedSuccess),
      ),
      overrides: [keyValueStoreProvider.overrideWithValue(store)],
    );
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('order_paid_light.png'),
    );
  });
}
