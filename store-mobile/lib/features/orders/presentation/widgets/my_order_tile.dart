import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_radius.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/features/orders/presentation/view_models/my_orders_view_model.dart';
import 'package:matjar_app/features/orders/presentation/widgets/order_date_format.dart';
import 'package:matjar_app/features/orders/presentation/widgets/order_status_chip.dart';

/// One saved order on a rounded panel: reference, date, items, total and
/// its current status.
class MyOrderTile extends StatelessWidget {
  const MyOrderTile({required this.entry, required this.onTap, super.key});

  final MyOrderEntry entry;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final textTheme = Theme.of(context).textTheme;
    final palette = context.palette;
    final details = entry.details;
    final createdAt = details?.createdAt ?? entry.saved.createdAt;
    final subtitle = [
      formatOrderDate(context, createdAt),
      if (details != null) l10n.orderItemsCount(details.itemCount),
    ].join(' · ');

    return Material(
      color: palette.panel,
      borderRadius: BorderRadius.circular(AppRadius.panel),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsetsDirectional.all(AppSpacing.xl),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(entry.saved.reference, style: textTheme.titleSmall),
                    const SizedBox(height: AppSpacing.xxs),
                    Text(
                      subtitle,
                      style: textTheme.bodySmall?.copyWith(
                        color: palette.mutedText,
                      ),
                    ),
                    if (entry.failure != null) ...[
                      const SizedBox(height: AppSpacing.xs),
                      Text(
                        l10n.orderStatusUnavailable,
                        style: textTheme.bodySmall?.copyWith(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              if (details != null) ...[
                const SizedBox(width: AppSpacing.md),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(l10n.sdg(details.total), style: textTheme.titleSmall),
                    const SizedBox(height: AppSpacing.sm),
                    OrderStatusChip(status: details.status),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
