import 'package:flutter/foundation.dart';
import 'package:matjar_app/features/orders/data/models/order_status.dart';

@immutable
class OrderItem {
  const OrderItem({
    required this.name,
    required this.quantity,
    required this.unitAmount,
    required this.imageUrl,
  });

  factory OrderItem.fromJson(
    Map<String, Object?> json, {
    required String Function(String url) rewriteUrl,
  }) {
    if (json case {
      'name': final String name,
      'quantity': final int quantity,
      'unit_amount': final int unitAmount,
    }) {
      final imageUrl = json['image_url'];
      return OrderItem(
        name: name,
        quantity: quantity,
        unitAmount: unitAmount,
        imageUrl: imageUrl is String && imageUrl.isNotEmpty
            ? rewriteUrl(imageUrl)
            : null,
      );
    }
    throw FormatException('Invalid order item: $json');
  }

  final String name;
  final int quantity;
  final int unitAmount;
  final String? imageUrl;
}

/// Response of `GET /api/orders/{reference}`. This, not the browser redirect,
/// is what decides whether an order is paid.
@immutable
class OrderDetails {
  const OrderDetails({
    required this.reference,
    required this.status,
    required this.total,
    required this.totalFormatted,
    required this.items,
    required this.invoice,
    required this.paidAt,
    required this.createdAt,
  });

  factory OrderDetails.fromJson(
    Map<String, Object?> json, {
    required String Function(String url) rewriteUrl,
  }) {
    if (json case {
      'reference': final String reference,
      'status': final String status,
      'total': final int total,
      'created_at': final String createdAt,
    }) {
      final items = json['items'];
      final paidAt = json['paid_at'];
      final invoice = json['invoice'];
      return OrderDetails(
        reference: reference,
        status: OrderStatus.fromApi(status),
        total: total,
        totalFormatted: json['total_formatted'] is String
            ? json['total_formatted']! as String
            : '',
        items: [
          if (items is List<Object?>)
            for (final item in items)
              OrderItem.fromJson(
                item is Map<String, Object?>
                    ? item
                    : throw FormatException('Invalid order item: $item'),
                rewriteUrl: rewriteUrl,
              ),
        ],
        invoice: invoice is String && invoice.isNotEmpty ? invoice : null,
        paidAt: paidAt is String ? DateTime.parse(paidAt) : null,
        createdAt: DateTime.parse(createdAt),
      );
    }
    throw FormatException('Invalid order: ${json.keys}');
  }

  final String reference;
  final OrderStatus status;
  final int total;
  final String totalFormatted;
  final List<OrderItem> items;
  final String? invoice;
  final DateTime? paidAt;
  final DateTime createdAt;

  int get itemCount => items.fold(0, (sum, item) => sum + item.quantity);
}
