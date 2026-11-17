import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:matjar_app/core/error/failure.dart';
import 'package:matjar_app/core/error/result.dart';
import 'package:matjar_app/features/orders/data/models/order_details.dart';
import 'package:matjar_app/features/orders/data/models/saved_order.dart';
import 'package:matjar_app/features/orders/data/orders_repository.dart';
import 'package:matjar_app/features/orders/data/saved_orders_store.dart';

@immutable
class MyOrderEntry {
  const MyOrderEntry({required this.saved, this.details, this.failure});

  final SavedOrder saved;
  final OrderDetails? details;
  final Failure? failure;
}

@immutable
sealed class MyOrdersState {
  const MyOrdersState();
}

final class MyOrdersLoading extends MyOrdersState {
  const MyOrdersLoading();
}

final class MyOrdersLoaded extends MyOrdersState {
  const MyOrdersLoaded(this.entries);

  final List<MyOrderEntry> entries;
}

final myOrdersViewModelProvider =
    NotifierProvider.autoDispose<MyOrdersViewModel, MyOrdersState>(
      MyOrdersViewModel.new,
    );

/// Lists the orders saved on this device with their current status.
class MyOrdersViewModel extends Notifier<MyOrdersState> {
  @override
  MyOrdersState build() {
    unawaited(Future.microtask(refresh));
    return const MyOrdersLoading();
  }

  Future<void> refresh() async {
    final saved = await ref.read(savedOrdersStoreProvider).readAll();
    final repository = ref.read(ordersRepositoryProvider);
    final entries = await Future.wait([
      for (final order in saved)
        repository
            .fetchOrder(
              reference: order.reference,
              accessToken: order.accessToken,
            )
            .then(
              (result) => switch (result) {
                Success(:final data) => MyOrderEntry(
                  saved: order,
                  details: data,
                ),
                Err(:final failure) => MyOrderEntry(
                  saved: order,
                  failure: failure,
                ),
              },
            ),
    ]);
    if (ref.mounted) {
      state = MyOrdersLoaded(entries);
    }
  }
}
