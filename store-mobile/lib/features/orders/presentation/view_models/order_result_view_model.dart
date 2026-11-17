import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:matjar_app/core/error/failure.dart';
import 'package:matjar_app/core/error/result.dart';
import 'package:matjar_app/features/cart/presentation/view_models/cart_notifier.dart';
import 'package:matjar_app/features/orders/data/models/order_details.dart';
import 'package:matjar_app/features/orders/data/models/order_status.dart';
import 'package:matjar_app/features/orders/data/orders_repository.dart';
import 'package:matjar_app/features/orders/data/saved_orders_store.dart';

/// How the shopper reached the order result screen.
enum PaymentOrigin {
  /// The gateway redirected to Matjar's success URL.
  returnedSuccess,

  /// The gateway redirected to Matjar's cancel URL.
  returnedCancel,

  /// The shopper closed the payment page.
  closedByUser,

  /// Opened from "My orders".
  history;

  static PaymentOrigin fromName(String? name) =>
      values.where((origin) => origin.name == name).firstOrNull ?? history;

  bool get followsPayment => this != history;
}

/// How often and how long to re-check a pending order.
@immutable
class PollingPolicy {
  const PollingPolicy({required this.interval, required this.maxAttempts});

  final Duration interval;
  final int maxAttempts;
}

final pollingPolicyProvider = Provider<PollingPolicy>(
  (ref) => const PollingPolicy(interval: Duration(seconds: 2), maxAttempts: 10),
);

@immutable
class OrderResultState {
  const OrderResultState({
    required this.origin,
    this.order,
    this.failure,
    this.isChecking = false,
  });

  final PaymentOrigin origin;

  /// Last status Matjar reported. Only this decides what the screen says.
  final OrderDetails? order;

  /// Last error; ignored while an [order] is already known and a later
  /// re-check fails.
  final Failure? failure;
  final bool isChecking;

  OrderResultState copyWith({
    OrderDetails? order,
    Failure? failure,
    bool clearFailure = false,
    bool? isChecking,
  }) => OrderResultState(
    origin: origin,
    order: order ?? this.order,
    failure: clearFailure ? null : failure ?? this.failure,
    isChecking: isChecking ?? this.isChecking,
  );
}

typedef OrderResultArgs = ({String reference, PaymentOrigin origin});

final orderResultViewModelProvider = NotifierProvider.autoDispose
    .family<OrderResultViewModel, OrderResultState, OrderResultArgs>(
      OrderResultViewModel.new,
    );

/// Asks Matjar for the order status after the payment page closes.
///
/// After a redirect it polls while the order is `pending`, because Matjar
/// confirms with the gateway server-side and that can take a moment.
class OrderResultViewModel extends Notifier<OrderResultState> {
  OrderResultViewModel(this.args);

  final OrderResultArgs args;
  bool _inFlight = false;

  @override
  OrderResultState build() {
    unawaited(
      Future.microtask(
        () => check(
          pollWhilePending:
              args.origin == PaymentOrigin.returnedSuccess ||
              args.origin == PaymentOrigin.returnedCancel,
        ),
      ),
    );
    return OrderResultState(origin: args.origin, isChecking: true);
  }

  /// Fetches the status once, or until it is final when [pollWhilePending].
  Future<void> check({bool pollWhilePending = true}) async {
    if (_inFlight) {
      return;
    }
    _inFlight = true;
    try {
      await _check(pollWhilePending: pollWhilePending);
    } finally {
      _inFlight = false;
    }
  }

  Future<void> _check({required bool pollWhilePending}) async {
    state = state.copyWith(isChecking: true, clearFailure: true);

    final saved = await ref.read(savedOrdersStoreProvider).find(args.reference);
    if (!ref.mounted) {
      return;
    }
    if (saved == null) {
      state = state.copyWith(
        isChecking: false,
        failure: const NotFoundFailure('No access token saved for this order'),
      );
      return;
    }

    final policy = ref.read(pollingPolicyProvider);
    final repository = ref.read(ordersRepositoryProvider);
    for (var attempt = 1; attempt <= policy.maxAttempts; attempt++) {
      final result = await repository.fetchOrder(
        reference: saved.reference,
        accessToken: saved.accessToken,
      );
      if (!ref.mounted) {
        return;
      }
      switch (result) {
        case Success(:final data):
          state = state.copyWith(order: data, clearFailure: true);
          if (data.status == OrderStatus.paid && args.origin.followsPayment) {
            ref.read(cartProvider.notifier).clear();
          }
          if (data.status.isFinal) {
            state = state.copyWith(isChecking: false);
            return;
          }
        case Err(:final failure):
          state = state.copyWith(failure: failure);
          if (failure is NotFoundFailure) {
            state = state.copyWith(isChecking: false);
            return;
          }
      }
      if (!pollWhilePending || attempt == policy.maxAttempts) {
        break;
      }
      await Future<void>.delayed(policy.interval);
      if (!ref.mounted) {
        return;
      }
    }
    state = state.copyWith(isChecking: false);
  }
}
