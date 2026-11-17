import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_radius.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/features/orders/data/models/order_status.dart';
import 'package:matjar_app/features/orders/presentation/widgets/order_status_style.dart';

/// Small status pill.
class OrderStatusChip extends StatelessWidget {
  const OrderStatusChip({required this.status, super.key});

  final OrderStatus status;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: status.background(context),
        borderRadius: BorderRadius.circular(AppRadius.pill),
      ),
      child: Padding(
        padding: const EdgeInsetsDirectional.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.xs,
        ),
        child: Text(
          status.label(context.l10n),
          style: Theme.of(context).textTheme.labelSmall
              ?.copyWith(color: status.foreground(context)),
        ),
      ),
    );
  }
}
