import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_radius.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/theme/breakpoints.dart';
import 'package:matjar_app/core/widgets/eyebrow.dart';
import 'package:matjar_app/core/widgets/primary_button.dart';
import 'package:matjar_app/core/widgets/product_image.dart';
import 'package:matjar_app/features/products/data/models/product.dart';

/// Rounded hero panel: eyebrow, bold three-line headline, sub-text, a lime
/// call to action, and a large product photo (under the text on phones,
/// beside it on wider screens).
class ProductsHero extends StatelessWidget {
  const ProductsHero({
    required this.featured,
    required this.onBrowse,
    super.key,
  });

  /// Product whose photo fills the hero; null while loading.
  final Product? featured;
  final VoidCallback onBrowse;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final textTheme = Theme.of(context).textTheme;
    final palette = context.palette;

    final copy = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Eyebrow(l10n.heroEyebrow),
        const SizedBox(height: AppSpacing.lg),
        Semantics(
          header: true,
          child: Text(l10n.heroHeadline, style: textTheme.displaySmall),
        ),
        const SizedBox(height: AppSpacing.md),
        Text(
          l10n.heroBody,
          style: textTheme.bodyLarge?.copyWith(color: palette.mutedText),
        ),
        const SizedBox(height: AppSpacing.xxl),
        PrimaryButton(
          label: l10n.heroCta,
          icon: Icons.arrow_forward,
          expand: false,
          onPressed: onBrowse,
        ),
      ],
    );

    final product = featured;
    final photo = product == null
        ? null
        : ExcludeSemantics(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(AppRadius.tile),
              child: AspectRatio(
                aspectRatio: AppSizes.heroImageAspectRatio,
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
          );

    return DecoratedBox(
      decoration: BoxDecoration(
        color: palette.panel,
        borderRadius: BorderRadius.circular(AppRadius.panel),
      ),
      child: Padding(
        padding: const EdgeInsetsDirectional.all(AppSpacing.xxl),
        child: LayoutBuilder(
          builder: (context, constraints) {
            if (photo == null) {
              return copy;
            }
            if (constraints.maxWidth >= Breakpoints.medium) {
              return Row(
                children: [
                  Expanded(child: copy),
                  const SizedBox(width: AppSpacing.xxl),
                  Expanded(child: photo),
                ],
              );
            }
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                copy,
                const SizedBox(height: AppSpacing.xxl),
                photo,
              ],
            );
          },
        ),
      ),
    );
  }
}
