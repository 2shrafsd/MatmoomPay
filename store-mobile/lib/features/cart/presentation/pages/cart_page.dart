import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/app/router/routes.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/widgets/content_width.dart';
import 'package:matjar_app/core/widgets/panel.dart';
import 'package:matjar_app/core/widgets/primary_button.dart';
import 'package:matjar_app/core/widgets/state_message.dart';
import 'package:matjar_app/features/cart/presentation/view_models/cart_notifier.dart';
import 'package:matjar_app/features/cart/presentation/widgets/cart_line_tile.dart';
import 'package:matjar_app/features/cart/presentation/widgets/checkout_form.dart';

class CartPage extends ConsumerWidget {
  const CartPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final cart = ref.watch(cartProvider);
    final notifier = ref.read(cartProvider.notifier);
    final textTheme = Theme.of(context).textTheme;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.cartTitle)),
      body: cart.isEmpty
          ? StateMessage(
              icon: Icons.shopping_bag_outlined,
              title: l10n.cartEmptyTitle,
              body: l10n.cartEmptyBody,
              actions: [
                PrimaryButton(
                  label: l10n.browseProducts,
                  onPressed: () => context.go(Routes.products),
                ),
              ],
            )
          : SafeArea(
              child: SingleChildScrollView(
                padding: const EdgeInsetsDirectional.all(AppSpacing.lg),
                child: ContentWidth(
                  maxWidth: AppSpacing.maxFormWidth,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Panel(
                        padding: const EdgeInsetsDirectional.symmetric(
                          horizontal: AppSpacing.lg,
                          vertical: AppSpacing.xs,
                        ),
                        child: Column(
                          children: [
                            for (final (index, line) in cart.lines.indexed) ...[
                              if (index > 0)
                                const Divider(height: AppSizes.hairline),
                              CartLineTile(
                                key: ValueKey(line.product.slug),
                                line: line,
                                onIncrement: () =>
                                    notifier.increment(line.product.slug),
                                onDecrement: () =>
                                    notifier.decrement(line.product.slug),
                                onRemove: () =>
                                    notifier.remove(line.product.slug),
                              ),
                            ],
                            const Divider(height: AppSizes.hairline),
                            Padding(
                              padding: const EdgeInsetsDirectional.symmetric(
                                vertical: AppSpacing.lg,
                              ),
                              child: Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      l10n.cartTotal,
                                      style: textTheme.bodyLarge,
                                    ),
                                  ),
                                  Text(
                                    l10n.sdg(cart.total),
                                    style: textTheme.titleLarge,
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: AppSpacing.lg),
                      Panel(child: CheckoutForm(total: cart.total)),
                    ],
                  ),
                ),
              ),
            ),
    );
  }
}
