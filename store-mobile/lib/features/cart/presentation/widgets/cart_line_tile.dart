import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_radius.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/widgets/product_image.dart';
import 'package:matjar_app/features/cart/data/models/cart_line.dart';

/// One cart line: photo tile, name, price, quantity stepper and remove.
class CartLineTile extends StatelessWidget {
  const CartLineTile({
    required this.line,
    required this.onIncrement,
    required this.onDecrement,
    required this.onRemove,
    super.key,
  });

  final CartLine line;
  final VoidCallback onIncrement;
  final VoidCallback onDecrement;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final textTheme = Theme.of(context).textTheme;
    final muted = context.palette.mutedText;
    final product = line.product;
    final stepperStyle = IconButton.styleFrom(
      backgroundColor: context.palette.panelRaised,
      tapTargetSize: MaterialTapTargetSize.padded,
    );
    final thumbnailPixels =
        (AppSizes.thumbnail * MediaQuery.devicePixelRatioOf(context)).round();

    return Padding(
      padding: const EdgeInsetsDirectional.symmetric(vertical: AppSpacing.lg),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(AppRadius.input),
            child: SizedBox.square(
              dimension: AppSizes.thumbnail,
              child: ProductImage(
                imageUrl: product.imageUrl,
                colorHex: product.colorHex,
                name: product.name,
                memCacheWidth: thumbnailPixels,
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.lg),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(product.name, style: textTheme.titleSmall),
                Text(
                  l10n.sdg(product.price),
                  style: textTheme.bodySmall?.copyWith(color: muted),
                ),
                const SizedBox(height: AppSpacing.xs),
                Wrap(
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    IconButton(
                      style: stepperStyle,
                      tooltip: l10n.decreaseQuantity,
                      onPressed: line.quantity > CartState.minQuantity
                          ? onDecrement
                          : null,
                      icon: const Icon(Icons.remove, size: AppSizes.iconSm),
                    ),
                    Semantics(
                      label: l10n.quantityValue(line.quantity),
                      excludeSemantics: true,
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(
                          minWidth: AppSizes.iconLg,
                        ),
                        child: Text(
                          '${line.quantity}',
                          textAlign: TextAlign.center,
                          style: textTheme.bodyLarge,
                        ),
                      ),
                    ),
                    IconButton(
                      style: stepperStyle,
                      tooltip: l10n.increaseQuantity,
                      onPressed: line.quantity < CartState.maxQuantity
                          ? onIncrement
                          : null,
                      icon: const Icon(Icons.add, size: AppSizes.iconSm),
                    ),
                  ],
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              IconButton(
                tooltip: l10n.removeItem,
                onPressed: onRemove,
                icon: Icon(Icons.close, size: AppSizes.iconSm, color: muted),
              ),
              Text(l10n.sdg(line.subtotal), style: textTheme.titleSmall),
            ],
          ),
        ],
      ),
    );
  }
}
