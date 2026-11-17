import 'package:flutter_test/flutter_test.dart';
import 'package:matjar_app/core/error/failure.dart';
import 'package:matjar_app/core/error/result.dart';
import 'package:matjar_app/core/network/loopback_url_rewriter.dart';
import 'package:matjar_app/features/cart/data/models/cart_line.dart';
import 'package:matjar_app/features/orders/data/models/created_order.dart';
import 'package:matjar_app/features/orders/data/models/order_details.dart';
import 'package:matjar_app/features/orders/data/models/order_status.dart';
import 'package:matjar_app/features/orders/data/orders_repository.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/fixtures.dart';

void main() {
  late FakeHttpAdapter adapter;

  OrdersRepository repositoryWith(FakeHandler handler) {
    adapter = FakeHttpAdapter(handler);
    return OrdersRepository(
      dio: fakeDio(adapter),
      urlRewriter: const LoopbackUrlRewriter(targetHost: '10.0.2.2'),
    );
  }

  Future<Result<CreatedOrder>> create(OrdersRepository repository) =>
      repository.createOrder(
        customerName: '  آمنة عثمان ',
        customerPhone: '+249 912 345 678',
        lines: const [
          CartLine(product: studioHeadphones, quantity: 2),
          CartLine(product: proEarbuds, quantity: 1),
        ],
      );

  group('createOrder', () {
    test('posts the contract body and parses the checkout session', () async {
      final repository = repositoryWith(
        (_) => FakeReply(201, createdOrderJson()),
      );

      final result = await create(repository);

      final request = adapter.requests.single;
      expect(request.method, 'POST');
      expect(request.path, '/api/orders');
      expect(request.data, {
        'customer_name': 'آمنة عثمان',
        'customer_phone': '+249 912 345 678',
        'items': [
          {'slug': 'studio-headphones', 'quantity': 2},
          {'slug': 'pro-earbuds', 'quantity': 1},
        ],
      });

      final order = (result as Success<CreatedOrder>).data;
      expect(order.reference, 'MJ-24A7Q');
      expect(order.status, OrderStatus.pending);
      expect(order.total, 32500);
      expect(order.accessToken, 'tok_9c1e5d');
      // Loopback URLs are rewritten for the Android emulator.
      expect(order.paymentUrl, 'http://10.0.2.2:8000/checkout/cs_test_81f2');
      expect(
        order.successUrlPrefix,
        'http://10.0.2.2:8001/orders/MJ-24A7Q/return',
      );
      expect(
        order.cancelUrlPrefix,
        'http://10.0.2.2:8001/orders/MJ-24A7Q/return?cancelled=1',
      );
    });

    test('422 keeps the first message of each field', () async {
      final repository = repositoryWith(
        (_) => const FakeReply(422, {
          'message': 'رقم الهاتف غير صحيح.',
          'errors': {
            'customer_phone': ['رقم الهاتف غير صحيح.', 'second'],
            'items.0.slug': ['المنتج غير متوفر.'],
          },
        }),
      );

      final failure = (await create(repository) as Err<CreatedOrder>).failure;

      expect(failure, isA<ValidationFailure>());
      expect((failure as ValidationFailure).fieldErrors, {
        'customer_phone': 'رقم الهاتف غير صحيح.',
        'items.0.slug': 'المنتج غير متوفر.',
      });
      expect(failure.message, 'رقم الهاتف غير صحيح.');
    });

    test('502 means the gateway is unreachable', () async {
      final repository = repositoryWith(
        (_) => const FakeReply(502, {'message': 'Payment gateway unavailable'}),
      );
      final failure = (await create(repository) as Err<CreatedOrder>).failure;
      expect(failure, isA<GatewayUnavailableFailure>());
    });

    test('429 means throttled', () async {
      final repository = repositoryWith((_) => const FakeReply(429));
      final failure = (await create(repository) as Err<CreatedOrder>).failure;
      expect(failure, isA<ThrottledFailure>());
    });

    test('a session without payment_url is a parsing failure', () async {
      final json = createdOrderJson();
      (json['data']! as Map<String, Object?>).remove('payment_url');
      final repository = repositoryWith((_) => FakeReply(201, json));
      final failure = (await create(repository) as Err<CreatedOrder>).failure;
      expect(failure, isA<ParsingFailure>());
    });
  });

  group('fetchOrder', () {
    test('sends the order token and parses a paid order', () async {
      final repository = repositoryWith((_) => FakeReply(200, orderJson()));

      final result = await repository.fetchOrder(
        reference: 'MJ-24A7Q',
        accessToken: 'tok_9c1e5d',
      );

      final request = adapter.requests.single;
      expect(request.path, '/api/orders/MJ-24A7Q');
      expect(request.headers['X-Order-Token'], 'tok_9c1e5d');

      final order = (result as Success<OrderDetails>).data;
      expect(order.status, OrderStatus.paid);
      expect(order.invoice, 'INV-2026-0042');
      expect(order.paidAt, DateTime.utc(2026, 11, 17, 6, 41));
      expect(order.createdAt, DateTime.utc(2026, 11, 17, 6, 38, 12));
      expect(order.itemCount, 3);
      expect(
        order.items.first.imageUrl,
        'http://10.0.2.2:8001/images/studio-headphones.jpg',
      );
      expect(order.items.last.imageUrl, isNull);
    });

    test('a pending order has no invoice or payment time', () async {
      final repository = repositoryWith(
        (_) => FakeReply(
          200,
          orderJson(status: 'pending', invoice: null, paidAt: null),
        ),
      );
      final order = (await repository.fetchOrder(
        reference: 'MJ-24A7Q',
        accessToken: 't',
      ) as Success<OrderDetails>).data;
      expect(order.status, OrderStatus.pending);
      expect(order.invoice, isNull);
      expect(order.paidAt, isNull);
    });

    test('an unknown status is kept as OrderStatus.unknown', () async {
      final repository = repositoryWith(
        (_) => FakeReply(200, orderJson(status: 'refunded')),
      );
      final order = (await repository.fetchOrder(
        reference: 'MJ-24A7Q',
        accessToken: 't',
      ) as Success<OrderDetails>).data;
      expect(order.status, OrderStatus.unknown);
    });

    test('a wrong token (404) maps to NotFoundFailure', () async {
      final repository = repositoryWith(
        (_) => const FakeReply(404, {'message': 'Not Found'}),
      );
      final result = await repository.fetchOrder(
        reference: 'MJ-24A7Q',
        accessToken: 'wrong',
      );
      expect((result as Err<OrderDetails>).failure, isA<NotFoundFailure>());
    });
  });
}
