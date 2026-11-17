import 'package:flutter/foundation.dart';
import 'package:matjar_app/features/orders/data/models/order_status.dart';

/// Response of `POST /api/orders`: the order plus the hosted checkout page to
/// open and the return URLs to watch for.
@immutable
class CreatedOrder {
  const CreatedOrder({
    required this.reference,
    required this.status,
    required this.total,
    required this.totalFormatted,
    required this.paymentUrl,
    required this.accessToken,
    required this.successUrlPrefix,
    required this.cancelUrlPrefix,
  });

  factory CreatedOrder.fromJson(
    Map<String, Object?> json, {
    required String Function(String url) rewriteUrl,
  }) {
    if (json case {
      'reference': final String reference,
      'total': final int total,
      'payment_url': final String paymentUrl,
      'access_token': final String accessToken,
      'success_url_prefix': final String successUrlPrefix,
      'cancel_url_prefix': final String cancelUrlPrefix,
    }) {
      return CreatedOrder(
        reference: reference,
        status: OrderStatus.fromApi(json['status'] as String?),
        total: total,
        totalFormatted: json['total_formatted'] is String
            ? json['total_formatted']! as String
            : '',
        paymentUrl: rewriteUrl(paymentUrl),
        accessToken: accessToken,
        successUrlPrefix: rewriteUrl(successUrlPrefix),
        cancelUrlPrefix: rewriteUrl(cancelUrlPrefix),
      );
    }
    throw FormatException('Invalid created order: ${json.keys}');
  }

  final String reference;
  final OrderStatus status;
  final int total;
  final String totalFormatted;

  /// Hosted MatmoomPay checkout page, already rewritten for the device.
  final String paymentUrl;

  /// Lets this device read the order; sent as `X-Order-Token`.
  final String accessToken;
  final String successUrlPrefix;
  final String cancelUrlPrefix;
}
