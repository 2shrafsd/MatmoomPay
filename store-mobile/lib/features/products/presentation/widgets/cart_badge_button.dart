import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/app/router/routes.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/features/cart/presentation/view_models/cart_notifier.dart';

/// Thin cart icon with a tiny piece count.
class CartBadgeButton extends ConsumerWidget {
  const CartBadgeButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final count = ref.watch(cartProvider.select((cart) => cart.itemCount));
    final l10n = context.l10n;
    return Semantics(
      label: l10n.cartItemCount(count),
      button: true,
      excludeSemantics: true,
      child: IconButton(
        tooltip: l10n.cartTooltip,
        onPressed: () => context.push(Routes.cart),
        icon: Badge(
          isLabelVisible: count > 0,
          label: Text('$count'),
          child: const Icon(Icons.shopping_bag_outlined),
        ),
      ),
    );
  }
}
