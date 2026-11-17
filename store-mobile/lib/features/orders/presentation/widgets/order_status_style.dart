import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/features/orders/data/models/order_status.dart';
import 'package:matjar_app/l10n/generated/app_localizations.dart';

/// Label, icon and colours for an [OrderStatus]: paid uses the lime accent,
/// failure the error tint, everything else stays neutral.
extension OrderStatusStyle on OrderStatus {
  String label(AppLocalizations l10n) => switch (this) {
    OrderStatus.pending => l10n.statusLabelPending,
    OrderStatus.paid => l10n.statusLabelPaid,
    OrderStatus.failed => l10n.statusLabelFailed,
    OrderStatus.cancelled => l10n.statusLabelCancelled,
    OrderStatus.expired => l10n.statusLabelExpired,
    OrderStatus.abandoned => l10n.statusLabelAbandoned,
    OrderStatus.unknown => l10n.statusLabelUnknown,
  };

  IconData get icon => switch (this) {
    OrderStatus.pending => Icons.hourglass_empty,
    OrderStatus.paid => Icons.check_circle_outline,
    OrderStatus.failed => Icons.highlight_off,
    OrderStatus.cancelled => Icons.remove_circle_outline,
    OrderStatus.expired => Icons.timer_off_outlined,
    OrderStatus.abandoned => Icons.remove_shopping_cart_outlined,
    OrderStatus.unknown => Icons.help_outline,
  };

  Color foreground(BuildContext context) => switch (this) {
    OrderStatus.paid => context.palette.onAccent,
    OrderStatus.failed => Theme.of(context).colorScheme.error,
    OrderStatus.pending => Theme.of(context).colorScheme.onSurface,
    _ => context.palette.mutedText,
  };

  /// Pill / icon-circle background: lime for paid, error tint for failed.
  Color background(BuildContext context) => switch (this) {
    OrderStatus.paid => context.palette.accent,
    OrderStatus.failed => Theme.of(context).colorScheme.errorContainer,
    _ => context.palette.panelRaised,
  };
}
