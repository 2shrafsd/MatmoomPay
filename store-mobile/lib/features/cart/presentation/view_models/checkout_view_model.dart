import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:matjar_app/core/error/failure.dart';
import 'package:matjar_app/core/error/result.dart';
import 'package:matjar_app/features/cart/presentation/view_models/cart_notifier.dart';
import 'package:matjar_app/features/orders/data/models/created_order.dart';
import 'package:matjar_app/features/orders/data/models/saved_order.dart';
import 'package:matjar_app/features/orders/data/orders_repository.dart';
import 'package:matjar_app/features/orders/data/saved_orders_store.dart';

@immutable
sealed class CheckoutState {
  const CheckoutState();
}

final class CheckoutIdle extends CheckoutState {
  const CheckoutIdle();
}

final class CheckoutSubmitting extends CheckoutState {
  const CheckoutSubmitting();
}

final class CheckoutFailed extends CheckoutState {
  const CheckoutFailed(this.failure);

  final Failure failure;
}

final checkoutViewModelProvider =
    NotifierProvider.autoDispose<CheckoutViewModel, CheckoutState>(
      CheckoutViewModel.new,
    );

/// Turns the cart into a Matjar order and a MatmoomPay checkout session.
class CheckoutViewModel extends Notifier<CheckoutState> {
  @override
  CheckoutState build() => const CheckoutIdle();

  /// Clears a previous error once the shopper edits the form, so server-side
  /// field errors do not block the next attempt.
  void dismissFailure() {
    if (state is CheckoutFailed) {
      state = const CheckoutIdle();
    }
  }

  /// Returns the created order to open its payment page, or null when the
  /// request failed (the failure is in [state]).
  Future<CreatedOrder?> submit({
    required String customerName,
    required String customerPhone,
  }) async {
    final lines = ref.read(cartProvider).lines;
    if (state is CheckoutSubmitting || lines.isEmpty) {
      return null;
    }
    state = const CheckoutSubmitting();
    final result = await ref
        .read(ordersRepositoryProvider)
        .createOrder(
          customerName: customerName,
          customerPhone: customerPhone,
          lines: lines,
        );
    if (!ref.mounted) {
      return null;
    }
    switch (result) {
      case Success(:final data):
        // Saved before the payment page opens, so the order can be checked
        // later even if the app is killed mid-payment.
        await ref
            .read(savedOrdersStoreProvider)
            .save(
              SavedOrder(
                reference: data.reference,
                accessToken: data.accessToken,
                createdAt: DateTime.now(),
              ),
            );
        if (ref.mounted) {
          state = const CheckoutIdle();
        }
        return data;
      case Err(:final failure):
        state = CheckoutFailed(failure);
        return null;
    }
  }
}
