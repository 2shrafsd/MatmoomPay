import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_radius.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/widgets/primary_button.dart';
import 'package:matjar_app/core/widgets/product_image.dart';
import 'package:matjar_app/features/products/data/models/product.dart';
import 'package:matjar_app/features/products/presentation/view_models/products_query.dart';

/// A list of choices with a check mark on the current one.
class _ChoiceSheet<T> extends StatelessWidget {
  const _ChoiceSheet({
    required this.title,
    required this.options,
    required this.selected,
  });

  final String title;
  final List<(T, String)> options;
  final T selected;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(
              AppSpacing.xxl,
              0,
              AppSpacing.xxl,
              AppSpacing.sm,
            ),
            child: Text(title, style: Theme.of(context).textTheme.titleMedium),
          ),
          Flexible(
            child: ListView(
              shrinkWrap: true,
              children: [
                for (final (value, label) in options)
                  ListTile(
                    title: Text(label),
                    selected: value == selected,
                    trailing: value == selected
                        ? const Icon(Icons.check)
                        : null,
                    onTap: () => Navigator.of(context).pop((value,)),
                  ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
        ],
      ),
    );
  }
}

/// Asks for a sort order; null when dismissed.
Future<ProductSort?> showSortSheet(
  BuildContext context,
  ProductSort current,
) async {
  final l10n = context.l10n;
  final picked = await showModalBottomSheet<(ProductSort,)>(
    context: context,
    builder: (_) => _ChoiceSheet<ProductSort>(
      title: l10n.sort,
      selected: current,
      options: [
        (ProductSort.newest, l10n.sortNewest),
        (ProductSort.priceLowToHigh, l10n.sortPriceLowToHigh),
        (ProductSort.priceHighToLow, l10n.sortPriceHighToLow),
      ],
    ),
  );
  return picked?.$1;
}

/// Photo, name, price and description, with the add-to-cart action.
Future<void> showProductDetailsSheet(
  BuildContext context, {
  required Product product,
  required VoidCallback onAddToCart,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (sheetContext) => _ProductDetailsSheet(
      product: product,
      onAddToCart: () {
        Navigator.of(sheetContext).pop();
        onAddToCart();
      },
    ),
  );
}

class _ProductDetailsSheet extends StatelessWidget {
  const _ProductDetailsSheet({
    required this.product,
    required this.onAddToCart,
  });

  final Product product;
  final VoidCallback onAddToCart;

  /// The photo never takes more than this share of the screen height.
  static const double maxPhotoHeightFactor = 0.45;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final textTheme = Theme.of(context).textTheme;
    final maxPhoto = MediaQuery.sizeOf(context).height * maxPhotoHeightFactor;
    return SingleChildScrollView(
      padding: const EdgeInsetsDirectional.fromSTEB(
        AppSpacing.xxl,
        0,
        AppSpacing.xxl,
        AppSpacing.xxl,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: ConstrainedBox(
              constraints: BoxConstraints(
                maxHeight: maxPhoto,
                maxWidth: maxPhoto,
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(AppRadius.panel),
                child: AspectRatio(
                  aspectRatio: 1,
                  child: ProductImage(
                    imageUrl: product.imageUrl,
                    colorHex: product.colorHex,
                    name: product.name,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.xxl),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(product.name, style: textTheme.headlineSmall),
              ),
              const SizedBox(width: AppSpacing.md),
              Text(l10n.sdg(product.price), style: textTheme.titleMedium),
            ],
          ),
          if (product.description.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.md),
            Text(
              product.description,
              style: textTheme.bodyMedium?.copyWith(
                color: context.palette.mutedText,
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.xxl),
          PrimaryButton(label: l10n.addToCart, onPressed: onAddToCart),
        ],
      ),
    );
  }
}
