import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:matjar_app/core/di/core_providers.dart';
import 'package:matjar_app/core/storage/key_value_store.dart';
import 'package:matjar_app/features/orders/data/models/saved_order.dart';

final savedOrdersStoreProvider = Provider<SavedOrdersStore>(
  (ref) => SavedOrdersStore(ref.watch(keyValueStoreProvider)),
);

/// Orders created from this device, newest first, kept in secure storage
/// because each one carries an access token.
class SavedOrdersStore {
  const SavedOrdersStore(this._store);

  static const String storageKey = 'saved_orders_v1';
  static const int maxOrders = 50;

  final KeyValueStore _store;

  Future<List<SavedOrder>> readAll() async {
    final raw = await _store.read(storageKey);
    if (raw == null || raw.isEmpty) {
      return const [];
    }
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List<Object?>) {
        return const [];
      }
      return [
        for (final entry in decoded)
          if (entry is Map<String, Object?>) SavedOrder.fromJson(entry),
      ];
    } on FormatException {
      // A corrupted entry should not lock the shopper out of the app.
      return const [];
    }
  }

  Future<SavedOrder?> find(String reference) async {
    for (final order in await readAll()) {
      if (order.reference == reference) {
        return order;
      }
    }
    return null;
  }

  Future<void> save(SavedOrder order) async {
    final others = [
      for (final existing in await readAll())
        if (existing.reference != order.reference) existing,
    ];
    final updated = [order, ...others].take(maxOrders).toList();
    await _store.write(
      storageKey,
      jsonEncode([for (final entry in updated) entry.toJson()]),
    );
  }
}
