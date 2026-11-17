import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/app/router/routes.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/widgets/state_message.dart';
import 'package:matjar_app/features/orders/presentation/view_models/my_orders_view_model.dart';
import 'package:matjar_app/features/orders/presentation/widgets/my_order_tile.dart';

/// Orders placed from this device, with statuses refreshed from Matjar.
class MyOrdersPage extends ConsumerWidget {
  const MyOrdersPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final state = ref.watch(myOrdersViewModelProvider);
    final viewModel = ref.read(myOrdersViewModelProvider.notifier);

    return Scaffold(
      appBar: AppBar(title: Text(l10n.myOrdersTitle)),
      body: switch (state) {
        MyOrdersLoading() => const Center(
          child: CircularProgressIndicator(
            strokeWidth: AppSizes.progressBarHeight,
          ),
        ),
        MyOrdersLoaded(:final entries) => RefreshIndicator.adaptive(
          onRefresh: viewModel.refresh,
          child: entries.isEmpty
              ? CustomScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  slivers: [
                    SliverFillRemaining(
                      hasScrollBody: false,
                      child: StateMessage(
                        icon: Icons.receipt_long_outlined,
                        title: l10n.myOrdersEmptyTitle,
                        body: l10n.myOrdersEmptyBody,
                      ),
                    ),
                  ],
                )
              : ListView.separated(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsetsDirectional.all(AppSpacing.page),
                  itemCount: entries.length,
                  separatorBuilder: (_, _) =>
                      const SizedBox(height: AppSpacing.md),
                  itemBuilder: (context, index) {
                    final entry = entries[index];
                    return Center(
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(
                          maxWidth: AppSpacing.maxFormWidth,
                        ),
                        child: MyOrderTile(
                          key: ValueKey(entry.saved.reference),
                          entry: entry,
                          onTap: () async {
                            await context.push(
                              Routes.orderResult(entry.saved.reference),
                            );
                            await viewModel.refresh();
                          },
                        ),
                      ),
                    );
                  },
                ),
        ),
      },
    );
  }
}
