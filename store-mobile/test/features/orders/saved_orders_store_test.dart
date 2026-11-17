import 'package:flutter_test/flutter_test.dart';
import 'package:matjar_app/core/storage/key_value_store.dart';
import 'package:matjar_app/features/orders/data/models/saved_order.dart';
import 'package:matjar_app/features/orders/data/saved_orders_store.dart';

void main() {
  SavedOrder order(String reference, int day) => SavedOrder(
    reference: reference,
    accessToken: 'tok_$reference',
    createdAt: DateTime.utc(2026, 11, day),
  );

  test(
    'saves newest first, replaces duplicates and finds by reference',
    () async {
      final store = SavedOrdersStore(InMemoryKeyValueStore());
      await store.save(order('MJ-1', 1));
      await store.save(order('MJ-2', 2));
      await store.save(order('MJ-1', 3));

      final all = await store.readAll();
      expect(all.map((o) => o.reference), ['MJ-1', 'MJ-2']);
      expect(all.first.createdAt, DateTime.utc(2026, 11, 3));
      expect((await store.find('MJ-2'))?.accessToken, 'tok_MJ-2');
      expect(await store.find('MJ-9'), isNull);
    },
  );

  test('corrupted storage reads as no orders instead of crashing', () async {
    final store = SavedOrdersStore(
      InMemoryKeyValueStore({SavedOrdersStore.storageKey: '{not json'}),
    );
    expect(await store.readAll(), isEmpty);
  });
}
