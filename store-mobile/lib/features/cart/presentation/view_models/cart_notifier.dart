import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:matjar_app/features/cart/data/models/cart_line.dart';
import 'package:matjar_app/features/products/data/models/product.dart';

final cartProvider = NotifierProvider<CartNotifier, CartState>(
  CartNotifier.new,
);

/// Outcome of [CartNotifier.add], so the UI can explain a refusal.
enum AddToCartOutcome { added, limitReached }

class CartNotifier extends Notifier<CartState> {
  @override
  CartState build() => const CartState();

  /// Adds one piece of [product], or does nothing when it is already at
  /// [CartState.maxQuantity].
  AddToCartOutcome add(Product product) {
    final current = state.quantityOf(product.slug);
    if (current >= CartState.maxQuantity) {
      return AddToCartOutcome.limitReached;
    }
    if (current == 0) {
      state = CartState(
        lines: [
          ...state.lines,
          CartLine(product: product, quantity: 1),
        ],
      );
    } else {
      _setQuantity(product.slug, current + 1);
    }
    return AddToCartOutcome.added;
  }

  void increment(String slug) {
    final current = state.quantityOf(slug);
    if (current > 0 && current < CartState.maxQuantity) {
      _setQuantity(slug, current + 1);
    }
  }

  /// Lowers the quantity but never below [CartState.minQuantity]; removing a
  /// line is a separate, explicit action.
  void decrement(String slug) {
    final current = state.quantityOf(slug);
    if (current > CartState.minQuantity) {
      _setQuantity(slug, current - 1);
    }
  }

  void remove(String slug) {
    state = CartState(
      lines: [
        for (final line in state.lines)
          if (line.product.slug != slug) line,
      ],
    );
  }

  void clear() => state = const CartState();

  void _setQuantity(String slug, int quantity) {
    state = CartState(
      lines: [
        for (final line in state.lines)
          line.product.slug == slug ? line.copyWith(quantity: quantity) : line,
      ],
    );
  }
}
