import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_radius.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/widgets/accent_circle_icon.dart';
import 'package:matjar_app/core/widgets/product_image.dart';
import 'package:matjar_app/features/products/data/models/product.dart';

/// Grid item: photo on a rounded warm-grey tile with a small lime "+" in the
/// bottom-end corner, then name and price. Tapping the photo or the text
/// opens the details sheet.
class ProductTile extends StatelessWidget {
  const ProductTile({
    required this.product,
    required this.onAddToCart,
    required this.onOpenDetails,
    super.key,
  });

  final Product product;
  final VoidCallback onAddToCart;
  final VoidCallback onOpenDetails;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final l10n = context.l10n;
    final palette = context.palette;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AspectRatio(
          aspectRatio: 1,
          child: ClipRRect(
            borderRadius: BorderRadius.circular(AppRadius.tile),
            child: Stack(
              fit: StackFit.expand,
              children: [
                Semantics(
                  button: true,
                  label: product.name,
                  hint: l10n.productDetailsHint,
                  excludeSemantics: true,
                  child: GestureDetector(
                    onTap: onOpenDetails,
                    child: LayoutBuilder(
                      builder: (context, constraints) => ProductImage(
                        imageUrl: product.imageUrl,
                        colorHex: product.colorHex,
                        name: product.name,
                        memCacheWidth:
                            (constraints.maxWidth *
                                    MediaQuery.devicePixelRatioOf(context))
                                .round(),
                      ),
                    ),
                  ),
                ),
                PositionedDirectional(
                  bottom: 0,
                  end: 0,
                  // 48dp tap target around a 36dp lime circle.
                  child: IconButton(
                    tooltip: l10n.addToCart,
                    onPressed: onAddToCart,
                    padding: EdgeInsets.zero,
                    constraints: const BoxConstraints.tightFor(
                      width: AppSizes.minTapTarget,
                      height: AppSizes.minTapTarget,
                    ),
                    icon: AccentCircleIcon(
                      Icons.add,
                      diameter: AppSizes.addButton,
                      background: palette.accent,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        GestureDetector(
          onTap: onOpenDetails,
          behavior: HitTestBehavior.opaque,
          child: ExcludeSemantics(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  product.name,
                  style: textTheme.titleSmall,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: AppSpacing.xxs),
                Text(
                  l10n.sdg(product.price),
                  style: textTheme.bodySmall?.copyWith(
                    color: palette.mutedText,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
