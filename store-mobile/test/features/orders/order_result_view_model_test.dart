import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:matjar_app/core/error/failure.dart';
import 'package:matjar_app/core/error/result.dart';
import 'package:matjar_app/core/storage/key_value_store.dart';
import 'package:matjar_app/features/cart/data/models/cart_line.dart';
import 'package:matjar_app/features/cart/presentation/view_models/cart_notifier.dart';
import 'package:matjar_app/features/orders/data/models/created_order.dart';
import 'package:matjar_app/features/orders/data/models/order_details.dart';
import 'package:matjar_app/features/orders/data/models/order_status.dart';
import 'package:matjar_app/features/orders/data/models/saved_order.dart';
import 'package:matjar_app/features/orders/data/orders_repository.dart';
import 'package:matjar_app/features/orders/data/saved_orders_store.dart';
import 'package:matjar_app/features/orders/presentation/view_models/order_result_view_model.dart';

import '../../helpers/fixtures.dart';

/// Answers fetchOrder from a script of results, repeating the last one.
class ScriptedOrdersRepository implements OrdersRepository {
  ScriptedOrdersRepository(this.script);

  final List<Result<OrderDetails>> script;
  int calls = 0;
  String? lastToken;

  @override
  Future<Result<OrderDetails>> fetchOrder({
    required String reference,
    required String accessToken,
  }) async {
    lastToken = accessToken;
    final result = script[calls < script.length ? calls : script.length - 1];
    calls++;
    return result;
  }

  @override
  Future<Result<CreatedOrder>> createOrder({
    required String customerName,
    required String customerPhone,
    required List<CartLine> lines,
  }) => throw UnimplementedError();
}

void main() {
  late ScriptedOrdersRepository repository;
  late ProviderContainer container;

  Future<ProviderContainer> containerWith(
    List<Result<OrderDetails>> script, {
    bool saveToken = true,
  }) async {
    repository = ScriptedOrdersRepository(script);
    final store = SavedOrdersStore(InMemoryKeyValueStore());
    if (saveToken) {
      await store.save(
        SavedOrder(
          reference: 'MJ-24A7Q',
          accessToken: 'tok_9c1e5d',
          createdAt: DateTime.utc(2026, 11, 17),
        ),
      );
    }
    return ProviderContainer.test(
      overrides: [
        ordersRepositoryProvider.overrideWithValue(repository),
        savedOrdersStoreProvider.overrideWithValue(store),
        pollingPolicyProvider.overrideWithValue(
          const PollingPolicy(interval: Duration.zero, maxAttempts: 4),
        ),
      ],
    );
  }

  /// Reads the provider, keeps it alive and waits until checking stops.
  Future<OrderResultState> settle(OrderResultArgs args) async {
    final provider = orderResultViewModelProvider(args);
    container.listen(provider, (_, _) {});
    for (var i = 0; i < 100 && container.read(provider).isChecking; i++) {
      await Future<void>.delayed(Duration.zero);
    }
    return container.read(provider);
  }

  const afterRedirect = (
    reference: 'MJ-24A7Q',
    origin: PaymentOrigin.returnedSuccess,
  );

  test(
    'after a success redirect it polls while pending, then stops at paid',
    () async {
      container = await containerWith([
        Success(orderDetails(OrderStatus.pending)),
        Success(orderDetails(OrderStatus.pending)),
        Success(orderDetails(OrderStatus.paid)),
      ]);
      container.read(cartProvider.notifier).add(studioHeadphones);

      final state = await settle(afterRedirect);

      expect(repository.calls, 3);
      expect(repository.lastToken, 'tok_9c1e5d');
      expect(state.order?.status, OrderStatus.paid);
      expect(state.isChecking, isFalse);
      expect(
        container.read(cartProvider).isEmpty,
        isTrue,
        reason: 'paid clears the cart',
      );
    },
  );

  test('the redirect alone is not proof: still pending after polling stays pending', () async {
    container = await containerWith([
      Success(orderDetails(OrderStatus.pending)),
    ]);
    container.read(cartProvider.notifier).add(studioHeadphones);

    final state = await settle(afterRedirect);

    expect(repository.calls, 4, reason: 'maxAttempts');
    expect(state.order?.status, OrderStatus.pending);
    expect(state.isChecking, isFalse);
    expect(container.read(cartProvider).isEmpty, isFalse);
  });

  test('a failed payment stops polling immediately', () async {
    container = await containerWith([
      Success(orderDetails(OrderStatus.failed)),
    ]);
    final state = await settle(afterRedirect);
    expect(repository.calls, 1);
    expect(state.order?.status, OrderStatus.failed);
  });

  test('closing the page checks once without polling', () async {
    container = await containerWith([
      Success(orderDetails(OrderStatus.pending)),
    ]);
    final state = await settle((
      reference: 'MJ-24A7Q',
      origin: PaymentOrigin.closedByUser,
    ));
    expect(repository.calls, 1);
    expect(state.origin, PaymentOrigin.closedByUser);
    expect(state.order?.status, OrderStatus.pending);
  });

  test('a transient network error keeps polling and recovers', () async {
    container = await containerWith([
      const Err(NetworkFailure('offline')),
      Success(orderDetails(OrderStatus.paid)),
    ]);
    final state = await settle(afterRedirect);
    expect(repository.calls, 2);
    expect(state.order?.status, OrderStatus.paid);
    expect(state.failure, isNull);
  });

  test(
    'without a saved token it reports NotFound and never calls the API',
    () async {
      container = await containerWith(const [], saveToken: false);
      final state = await settle(afterRedirect);
      expect(repository.calls, 0);
      expect(state.failure, isA<NotFoundFailure>());
    },
  );

  test('history views do not clear the cart even for a paid order', () async {
    container = await containerWith([Success(orderDetails(OrderStatus.paid))]);
    container.read(cartProvider.notifier).add(studioHeadphones);
    await settle((reference: 'MJ-24A7Q', origin: PaymentOrigin.history));
    expect(container.read(cartProvider).isEmpty, isFalse);
  });
}
