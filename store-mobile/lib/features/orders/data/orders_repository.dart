import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:matjar_app/core/di/core_providers.dart';
import 'package:matjar_app/core/error/result.dart';
import 'package:matjar_app/core/network/dio_failure_mapper.dart';
import 'package:matjar_app/core/network/loopback_url_rewriter.dart';
import 'package:matjar_app/features/cart/data/models/cart_line.dart';
import 'package:matjar_app/features/orders/data/models/created_order.dart';
import 'package:matjar_app/features/orders/data/models/order_details.dart';

final ordersRepositoryProvider = Provider<OrdersRepository>(
  (ref) => OrdersRepository(
    dio: ref.watch(dioProvider),
    urlRewriter: ref.watch(loopbackUrlRewriterProvider),
  ),
);

/// Creates orders and reads their status from Matjar. Matjar talks to
/// MatmoomPay with the secret key; this app never sees that key.
class OrdersRepository {
  const OrdersRepository({required this._dio, required this._urlRewriter});

  static const String orderTokenHeader = 'X-Order-Token';

  final Dio _dio;
  final LoopbackUrlRewriter _urlRewriter;

  /// Creates the order and its MatmoomPay checkout session.
  Future<Result<CreatedOrder>> createOrder({
    required String customerName,
    required String customerPhone,
    required List<CartLine> lines,
  }) async {
    try {
      final response = await _dio.post<Map<String, Object?>>(
        '/api/orders',
        data: {
          'customer_name': customerName.trim(),
          'customer_phone': customerPhone.trim(),
          'items': [
            for (final line in lines)
              {'slug': line.product.slug, 'quantity': line.quantity},
          ],
        },
      );
      return Success(
        CreatedOrder.fromJson(
          _dataOf(response.data),
          rewriteUrl: _urlRewriter.rewrite,
        ),
      );
    } on Object catch (error) {
      return Err(mapErrorToFailure(error));
    }
  }

  /// Reads the order's current status. A wrong token answers 404, which maps
  /// to `NotFoundFailure`.
  Future<Result<OrderDetails>> fetchOrder({
    required String reference,
    required String accessToken,
  }) async {
    try {
      final response = await _dio.get<Map<String, Object?>>(
        '/api/orders/${Uri.encodeComponent(reference)}',
        options: Options(headers: {orderTokenHeader: accessToken}),
      );
      return Success(
        OrderDetails.fromJson(
          _dataOf(response.data),
          rewriteUrl: _urlRewriter.rewrite,
        ),
      );
    } on Object catch (error) {
      return Err(mapErrorToFailure(error));
    }
  }

  Map<String, Object?> _dataOf(Map<String, Object?>? body) {
    if (body case {'data': final Map<String, Object?> data}) {
      return data;
    }
    throw FormatException('Missing data object: ${body?.keys}');
  }
}
