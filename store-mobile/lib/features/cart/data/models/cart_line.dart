import 'package:flutter/foundation.dart';
import 'package:matjar_app/features/products/data/models/product.dart';

@immutable
class CartLine {
  const CartLine({required this.product, required this.quantity});

  final Product product;
  final int quantity;

  int get subtotal => product.price * quantity;

  CartLine copyWith({int? quantity}) =>
      CartLine(product: product, quantity: quantity ?? this.quantity);

  @override
  bool operator ==(Object other) =>
      other is CartLine &&
      other.product == product &&
      other.quantity == quantity;

  @override
  int get hashCode => Object.hash(product, quantity);
}

/// The in-memory cart. Lines keep the order products were added in.
@immutable
class CartState {
  const CartState({this.lines = const []});

  /// Matjar accepts 1 to 10 of each product per order.
  static const int minQuantity = 1;
  static const int maxQuantity = 10;

  final List<CartLine> lines;

  bool get isEmpty => lines.isEmpty;

  /// Number of pieces, shown on the cart badge.
  int get itemCount => lines.fold(0, (sum, line) => sum + line.quantity);

  /// Total in whole Sudanese pounds. The server recalculates it; this is for
  /// display before the order exists.
  int get total => lines.fold(0, (sum, line) => sum + line.subtotal);

  int quantityOf(String slug) {
    for (final line in lines) {
      if (line.product.slug == slug) {
        return line.quantity;
      }
    }
    return 0;
  }
}
