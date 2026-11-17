import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/app/router/routes.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/widgets/content_width.dart';
import 'package:matjar_app/core/widgets/failure_view.dart';
import 'package:matjar_app/core/widgets/primary_button.dart';
import 'package:matjar_app/core/widgets/state_message.dart';
import 'package:matjar_app/features/cart/presentation/view_models/cart_notifier.dart';
import 'package:matjar_app/features/orders/data/models/order_details.dart';
import 'package:matjar_app/features/orders/data/models/order_status.dart';
import 'package:matjar_app/features/orders/presentation/view_models/order_result_view_model.dart';
import 'package:matjar_app/features/orders/presentation/widgets/order_details_card.dart';
import 'package:matjar_app/features/orders/presentation/widgets/order_status_style.dart';

/// What happened to the payment, according to Matjar (never according to
/// the redirect URL).
class OrderResultPage extends ConsumerWidget {
  const OrderResultPage({required this.args, super.key});

  final OrderResultArgs args;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final provider = orderResultViewModelProvider(args);
    final state = ref.watch(provider);
    final viewModel = ref.read(provider.notifier);
    final order = state.order;
    final failure = state.failure;

    final Widget body;
    if (order == null) {
      body = failure != null && !state.isChecking
          ? FailureView(
              failure: failure,
              onRetry: () => viewModel.check(pollWhilePending: false),
            )
          : const _ConfirmingView();
    } else if (order.status == OrderStatus.pending && state.isChecking) {
      body = const _ConfirmingView();
    } else {
      body = _OutcomeView(
        order: order,
        closedByUser: state.origin == PaymentOrigin.closedByUser,
        onCheckAgain: viewModel.check,
      );
    }

    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.orderResultTitle)),
      body: SafeArea(
        child: AnimatedSwitcher(
          duration: const Duration(milliseconds: 250),
          child: body,
        ),
      ),
    );
  }
}

class _ConfirmingView extends StatelessWidget {
  const _ConfirmingView();

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final textTheme = Theme.of(context).textTheme;
    return Center(
      child: Padding(
        padding: const EdgeInsetsDirectional.all(AppSpacing.xxl),
        child: Semantics(
          liveRegion: true,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const SizedBox.square(
                dimension: AppSizes.statusIcon,
                child: Padding(
                  padding: EdgeInsetsDirectional.all(AppSpacing.md),
                  child: CircularProgressIndicator(
                    strokeWidth: AppSizes.progressBarHeight,
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              Text(
                l10n.statusConfirmingTitle,
                style: textTheme.titleLarge,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                l10n.statusConfirmingBody,
                style: textTheme.bodyMedium?.copyWith(
                  color: context.palette.mutedText,
                ),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OutcomeView extends ConsumerWidget {
  const _OutcomeView({
    required this.order,
    required this.closedByUser,
    required this.onCheckAgain,
  });

  final OrderDetails order;
  final bool closedByUser;
  final VoidCallback onCheckAgain;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final status = order.status;
    final cartHasItems = ref.watch(cartProvider.select((c) => !c.isEmpty));
    // Closing the page leaves the order pending on the server; to the
    // shopper that is a cancellation.
    final showAsCancelledByUser = closedByUser && status == OrderStatus.pending;

    final (String title, String body) = switch (status) {
      _ when showAsCancelledByUser => (
        l10n.statusCancelledTitle,
        l10n.statusCancelledBody,
      ),
      OrderStatus.paid => (
        l10n.statusPaidTitle,
        l10n.statusPaidBody(l10n.sdg(order.total)),
      ),
      OrderStatus.pending => (l10n.statusPendingTitle, l10n.statusPendingBody),
      OrderStatus.failed => (l10n.statusFailedTitle, l10n.statusFailedBody),
      OrderStatus.cancelled => (
        l10n.statusCancelledTitle,
        l10n.statusCancelledBody,
      ),
      OrderStatus.expired => (l10n.statusExpiredTitle, l10n.statusExpiredBody),
      OrderStatus.abandoned => (
        l10n.statusAbandonedTitle,
        l10n.statusAbandonedBody,
      ),
      OrderStatus.unknown => (l10n.statusLabelUnknown, l10n.statusPendingBody),
    };

    final continueShopping = TextButton(
      onPressed: () => context.go(Routes.products),
      child: Text(l10n.continueShopping),
    );
    final checkAgain = TextButton(
      onPressed: onCheckAgain,
      child: Text(l10n.checkAgain),
    );
    final tryAgain = PrimaryButton(
      label: l10n.tryAgain,
      onPressed: () => context.go(cartHasItems ? Routes.cart : Routes.products),
    );

    final List<Widget> actions = switch (status) {
      OrderStatus.paid => [
        PrimaryButton(
          label: l10n.continueShopping,
          onPressed: () => context.go(Routes.products),
        ),
        TextButton(
          onPressed: () => context.go(Routes.myOrders),
          child: Text(l10n.myOrdersTitle),
        ),
      ],
      OrderStatus.pending when showAsCancelledByUser => [tryAgain, checkAgain],
      OrderStatus.pending || OrderStatus.unknown => [
        PrimaryButton(label: l10n.checkAgain, onPressed: onCheckAgain),
        continueShopping,
      ],
      _ => [tryAgain, continueShopping],
    };

    final icon = showAsCancelledByUser
        ? OrderStatus.cancelled.icon
        : status.icon;
    final shownStatus = showAsCancelledByUser ? OrderStatus.cancelled : status;
    final iconColor = shownStatus.foreground(context);
    final iconBackground = shownStatus == OrderStatus.paid
        ? shownStatus.background(context)
        : shownStatus == OrderStatus.failed
        ? shownStatus.background(context)
        : context.palette.panel;

    return SingleChildScrollView(
      padding: const EdgeInsetsDirectional.all(AppSpacing.lg),
      child: ContentWidth(
        maxWidth: AppSpacing.maxFormWidth,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            StateMessage(
              icon: icon,
              iconColor: iconColor,
              iconBackground: iconBackground,
              title: title,
              body: body,
              actions: actions,
            ),
            OrderDetailsCard(order: order),
          ],
        ),
      ),
    );
  }
}
