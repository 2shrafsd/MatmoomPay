import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/widgets/eyebrow.dart';

/// «المنتجات ——» eyebrow, the bold section title and the sort button.
class ProductsSectionHeader extends StatelessWidget {
  const ProductsSectionHeader({
    required this.title,
    required this.onSort,
    super.key,
  });

  final String title;
  final VoidCallback onSort;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Padding(
      padding: const EdgeInsetsDirectional.fromSTEB(
        AppSpacing.page,
        0,
        AppSpacing.xs,
        AppSpacing.sm,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Eyebrow(l10n.productsEyebrow),
                const SizedBox(height: AppSpacing.xs),
                Semantics(
                  header: true,
                  child: Text(
                    title,
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: l10n.sort,
            onPressed: onSort,
            icon: const Icon(Icons.tune),
          ),
        ],
      ),
    );
  }
}
