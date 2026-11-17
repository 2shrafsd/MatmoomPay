import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/widgets/panel.dart';
import 'package:matjar_app/features/orders/data/models/order_details.dart';
import 'package:matjar_app/features/orders/presentation/widgets/order_date_format.dart';

/// Reference, amount, invoice and payment time of an order.
class OrderDetailsCard extends StatelessWidget {
  const OrderDetailsCard({required this.order, super.key});

  final OrderDetails order;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final paidAt = order.paidAt;
    final invoice = order.invoice;
    return Panel(
      child: Column(
        children: [
          _DetailRow(label: l10n.orderReference, value: order.reference),
          _DetailRow(label: l10n.orderTotal, value: l10n.sdg(order.total)),
          if (invoice != null)
            _DetailRow(label: l10n.invoiceNumber, value: invoice),
          if (paidAt != null)
            _DetailRow(
              label: l10n.paidAt,
              value: formatOrderDate(context, paidAt),
            ),
        ],
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Padding(
      padding: const EdgeInsetsDirectional.symmetric(vertical: AppSpacing.xs),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(
              label,
              style: textTheme.bodyMedium?.copyWith(
                color: context.palette.mutedText,
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Flexible(
            child: SelectableText(
              value,
              style: textTheme.bodyMedium,
              textAlign: TextAlign.end,
            ),
          ),
        ],
      ),
    );
  }
}
