import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/di/core_providers.dart';
import 'package:matjar_app/core/error/failure.dart';
import 'package:matjar_app/core/error/result.dart';
import 'package:matjar_app/core/storage/key_value_store.dart';
import 'package:matjar_app/features/cart/data/models/cart_line.dart';
import 'package:matjar_app/features/cart/presentation/pages/cart_page.dart';
import 'package:matjar_app/features/cart/presentation/view_models/cart_notifier.dart';
import 'package:matjar_app/features/orders/data/models/created_order.dart';
import 'package:matjar_app/features/orders/data/models/order_details.dart';
import 'package:matjar_app/features/orders/data/orders_repository.dart';
import 'package:matjar_app/features/orders/data/saved_orders_store.dart';

import '../../helpers/fixtures.dart';
import '../../helpers/pump_app.dart';

class _PrefilledCart extends CartNotifier {
  _PrefilledCart(this.lines);

  final List<CartLine> lines;

  @override
  CartState build() => CartState(lines: lines);
}

class FakeOrdersRepository implements OrdersRepository {
  FakeOrdersRepository(this.createResult);

  final Result<CreatedOrder> createResult;
  final List<Map<String, Object>> createCalls = [];

  @override
  Future<Result<CreatedOrder>> createOrder({
    required String customerName,
    required String customerPhone,
    required List<CartLine> lines,
  }) async {
    createCalls.add({
      'name': customerName,
      'phone': customerPhone,
      'lines': lines.length,
    });
    return createResult;
  }

  @override
  Future<Result<OrderDetails>> fetchOrder({
    required String reference,
    required String accessToken,
  }) => throw UnimplementedError();
}

void main() {
  late FakeOrdersRepository repository;
  late KeyValueStore storage;

  Future<void> pumpCart(
    WidgetTester tester, {
    List<CartLine> lines = const [
      CartLine(product: studioHeadphones, quantity: 2),
      CartLine(product: proEarbuds, quantity: 1),
    ],
    Result<CreatedOrder>? createResult,
  }) async {
    tester.view.physicalSize = const Size(390, 1400) * 3;
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);
    repository = FakeOrdersRepository(createResult ?? Success(createdOrder()));
    storage = InMemoryKeyValueStore();
    await pumpApp(
      tester,
      const CartPage(),
      overrides: [
        cartProvider.overrideWith(() => _PrefilledCart(lines)),
        ordersRepositoryProvider.overrideWithValue(repository),
        keyValueStoreProvider.overrideWithValue(storage),
      ],
      routes: [
        GoRoute(
          path: '/payment',
          builder: (_, state) =>
              Text('payment:${(state.extra! as CreatedOrder).reference}'),
        ),
      ],
    );
    await tester.pumpAndSettle();
  }

  Future<void> fillForm(
    WidgetTester tester, {
    String phone = '+249 912 345 678',
  }) async {
    await tester.enterText(
      find.widgetWithText(TextFormField, 'الاسم'),
      'آمنة عثمان',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'رقم الهاتف'),
      phone,
    );
  }

  testWidgets('an empty cart explains itself', (tester) async {
    await pumpCart(tester, lines: const []);
    expect(find.text('سلتك فارغة'), findsOneWidget);
    expect(find.text('تصفّح المنتجات'), findsOneWidget);
  });

  testWidgets('shows lines, total and the pay amount; + and remove update it', (
    tester,
  ) async {
    await pumpCart(tester);

    expect(find.text('سماعة رأس استوديو'), findsOneWidget);
    expect(find.text('32,500 ج.س'), findsOneWidget);
    expect(find.text('ادفع 32,500 ج.س'), findsOneWidget);

    await tester.tap(find.byTooltip('زيادة الكمية').first);
    await tester.pump();
    expect(find.text('ادفع 44,500 ج.س'), findsOneWidget);

    await tester.tap(find.byTooltip('احذف من السلة').last);
    await tester.pump();
    expect(find.text('سماعات أذن برو'), findsNothing);
    expect(find.text('ادفع 36,000 ج.س'), findsOneWidget);
  });

  testWidgets('the minus button is disabled at quantity one', (tester) async {
    await pumpCart(
      tester,
      lines: const [CartLine(product: studioHeadphones, quantity: 1)],
    );
    final minus = tester.widget<IconButton>(
      find.widgetWithIcon(IconButton, Icons.remove),
    );
    expect(minus.onPressed, isNull);
  });

  testWidgets('validates name and phone before calling the server', (
    tester,
  ) async {
    await pumpCart(tester);

    await tester.tap(find.text('ادفع 32,500 ج.س'));
    await tester.pump();
    expect(find.text('اكتب اسمك'), findsOneWidget);
    expect(find.text('اكتب رقم هاتفك'), findsOneWidget);

    await fillForm(tester, phone: '0912');
    await tester.tap(find.text('ادفع 32,500 ج.س'));
    await tester.pump();
    expect(
      find.text('اكتب من 8 إلى 16 رقمًا، ويمكن أن يبدأ الرقم بـ +'),
      findsOneWidget,
    );
    expect(repository.createCalls, isEmpty);
  });

  testWidgets(
    'a valid form creates the order, saves its token and opens payment',
    (tester) async {
      await pumpCart(tester);
      await fillForm(tester);

      await tester.tap(find.text('ادفع 32,500 ج.س'));
      await tester.pumpAndSettle();

      expect(repository.createCalls.single, {
        'name': 'آمنة عثمان',
        'phone': '+249 912 345 678',
        'lines': 2,
      });
      expect(find.text('payment:MJ-24A7Q'), findsOneWidget);
      final saved = await SavedOrdersStore(storage).find('MJ-24A7Q');
      expect(saved?.accessToken, 'tok_9c1e5d');
    },
  );

  testWidgets('shows the server field error from a 422 under the field', (
    tester,
  ) async {
    await pumpCart(
      tester,
      createResult: const Err(
        ValidationFailure(
          'invalid',
          fieldErrors: {'customer_phone': 'هذا الرقم غير مقبول.'},
        ),
      ),
    );
    await fillForm(tester);
    await tester.tap(find.text('ادفع 32,500 ج.س'));
    await tester.pumpAndSettle();

    expect(find.text('هذا الرقم غير مقبول.'), findsOneWidget);
    expect(
      find.text('راجع البيانات المكتوبة ثم حاول مرة أخرى.'),
      findsOneWidget,
    );

    // Editing the field clears the server error so the shopper can retry.
    await tester.enterText(
      find.widgetWithText(TextFormField, 'رقم الهاتف'),
      '+249 912 000 111',
    );
    await tester.pump();
    expect(find.text('هذا الرقم غير مقبول.'), findsNothing);
  });

  testWidgets('a gateway outage (502) is explained', (tester) async {
    await pumpCart(
      tester,
      createResult: const Err(GatewayUnavailableFailure('down')),
    );
    await fillForm(tester);
    await tester.tap(find.text('ادفع 32,500 ج.س'));
    await tester.pumpAndSettle();
    expect(find.textContaining('بوابة الدفع لا تستجيب'), findsOneWidget);
  });
}
