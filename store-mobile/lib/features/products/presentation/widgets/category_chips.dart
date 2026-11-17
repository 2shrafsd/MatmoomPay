import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/features/products/presentation/widgets/category_labels.dart';

/// Horizontally scrolling category pills; the active one is solid ink.
/// «الكل» (null) always comes first.
class CategoryChips extends StatelessWidget {
  const CategoryChips({
    required this.categories,
    required this.selected,
    required this.onSelected,
    super.key,
  });

  final List<String> categories;
  final String? selected;
  final ValueChanged<String?> onSelected;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final options = <(String?, String)>[
      (null, l10n.allCategories),
      for (final category in categories)
        (category, categoryLabel(l10n, category)),
    ];
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsetsDirectional.symmetric(
        horizontal: AppSpacing.page,
      ),
      child: Row(
        children: [
          for (final (index, (value, label)) in options.indexed) ...[
            if (index > 0) const SizedBox(width: AppSpacing.sm),
            _Chip(
              label: label,
              isActive: value == selected,
              onTap: () => onSelected(value),
            ),
          ],
        ],
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({
    required this.label,
    required this.isActive,
    required this.onTap,
  });

  final String label;
  final bool isActive;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final background = isActive ? scheme.onSurface : context.palette.panel;
    final foreground = isActive ? scheme.surface : scheme.onSurface;
    return Semantics(
      selected: isActive,
      button: true,
      excludeSemantics: true,
      label: label,
      child: ConstrainedBox(
        // The pill is 40dp tall; the tap target stays 48dp.
        constraints: const BoxConstraints(minHeight: AppSizes.minTapTarget),
        child: Center(
          child: Material(
            color: background,
            shape: const StadiumBorder(),
            child: InkWell(
              onTap: onTap,
              customBorder: const StadiumBorder(),
              child: ConstrainedBox(
                constraints: const BoxConstraints(
                  minHeight: AppSizes.chipHeight,
                  minWidth: AppSizes.minTapTarget,
                ),
                child: Padding(
                  padding: const EdgeInsetsDirectional.symmetric(
                    horizontal: AppSpacing.lg,
                  ),
                  child: Center(
                    widthFactor: 1,
                    child: Text(
                      label,
                      style: Theme.of(context).textTheme.labelMedium
                          ?.copyWith(color: foreground),
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
