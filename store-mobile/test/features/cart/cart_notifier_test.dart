import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:matjar_app/features/cart/data/models/cart_line.dart';
import 'package:matjar_app/features/cart/presentation/view_models/cart_notifier.dart';

import '../../helpers/fixtures.dart';

void main() {
  late ProviderContainer container;
  CartNotifier cart() => container.read(cartProvider.notifier);
  CartState state() => container.read(cartProvider);

  setUp(() => container = ProviderContainer.test());

  test('starts empty', () {
    expect(state().isEmpty, isTrue);
    expect(state().itemCount, 0);
    expect(state().total, 0);
  });

  test('adding the same product twice bumps its quantity', () {
    cart()
      ..add(studioHeadphones)
      ..add(proEarbuds)
      ..add(studioHeadphones);

    expect(state().lines.map((l) => l.product.slug), [
      'studio-headphones',
      'pro-earbuds',
    ]);
    expect(state().quantityOf('studio-headphones'), 2);
    expect(state().itemCount, 3);
    expect(state().total, 2 * 12000 + 8500);
  });

  test('stops at 10 pieces of one product and says so', () {
    for (var i = 0; i < CartState.maxQuantity; i++) {
      expect(cart().add(studioHeadphones), AddToCartOutcome.added);
    }
    expect(cart().add(studioHeadphones), AddToCartOutcome.limitReached);
    cart().increment('studio-headphones');
    expect(state().quantityOf('studio-headphones'), CartState.maxQuantity);
  });

  test('decrement never goes below one; remove deletes the line', () {
    cart()
      ..add(studioHeadphones)
      ..increment('studio-headphones')
      ..decrement('studio-headphones')
      ..decrement('studio-headphones');
    expect(state().quantityOf('studio-headphones'), 1);

    cart().remove('studio-headphones');
    expect(state().isEmpty, isTrue);
  });

  test('increment and decrement ignore products not in the cart', () {
    cart()
      ..increment('studio-headphones')
      ..decrement('studio-headphones');
    expect(state().isEmpty, isTrue);
  });

  test('clear empties the cart', () {
    cart()
      ..add(studioHeadphones)
      ..add(miniSpeaker)
      ..clear();
    expect(state().isEmpty, isTrue);
  });
}
