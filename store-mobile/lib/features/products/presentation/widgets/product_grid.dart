import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/theme/breakpoints.dart';

/// Sliver grid whose rows grow to fit their tallest item, so long names and
/// large text sizes never overflow (a fixed aspect ratio would).
class SliverProductGrid extends StatelessWidget {
  const SliverProductGrid({
    required this.itemCount,
    required this.itemBuilder,
    super.key,
  });

  final int itemCount;
  final IndexedWidgetBuilder itemBuilder;

  @override
  Widget build(BuildContext context) {
    return SliverLayoutBuilder(
      builder: (context, constraints) {
        final columns = Breakpoints.productColumns(constraints.crossAxisExtent);
        final rowCount = (itemCount / columns).ceil();
        return SliverPadding(
          padding: const EdgeInsetsDirectional.fromSTEB(
            AppSpacing.page,
            AppSpacing.sm,
            AppSpacing.page,
            AppSpacing.huge,
          ),
          sliver: SliverList.separated(
            itemCount: rowCount,
            separatorBuilder: (_, _) =>
                const SizedBox(height: AppSpacing.gridRowGap),
            itemBuilder: (context, row) => IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (var column = 0; column < columns; column++) ...[
                    if (column > 0)
                      const SizedBox(width: AppSpacing.gridGutter),
                    Expanded(
                      child: row * columns + column < itemCount
                          ? itemBuilder(context, row * columns + column)
                          : const SizedBox.shrink(),
                    ),
                  ],
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
