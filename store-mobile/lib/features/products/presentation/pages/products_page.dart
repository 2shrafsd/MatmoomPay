import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/theme/app_typography.dart';
import 'package:matjar_app/core/widgets/content_width.dart';
import 'package:matjar_app/core/widgets/failure_view.dart';
import 'package:matjar_app/core/widgets/state_message.dart';
import 'package:matjar_app/features/cart/presentation/view_models/cart_notifier.dart';
import 'package:matjar_app/features/products/data/models/product.dart';
import 'package:matjar_app/features/products/presentation/view_models/products_query.dart';
import 'package:matjar_app/features/products/presentation/view_models/products_view_model.dart';
import 'package:matjar_app/features/products/presentation/widgets/app_bar_actions.dart';
import 'package:matjar_app/features/products/presentation/widgets/cart_badge_button.dart';
import 'package:matjar_app/features/products/presentation/widgets/category_chips.dart';
import 'package:matjar_app/features/products/presentation/widgets/category_labels.dart';
import 'package:matjar_app/features/products/presentation/widgets/features_row.dart';
import 'package:matjar_app/features/products/presentation/widgets/product_grid.dart';
import 'package:matjar_app/features/products/presentation/widgets/product_grid_skeleton.dart';
import 'package:matjar_app/features/products/presentation/widgets/product_sheets.dart';
import 'package:matjar_app/features/products/presentation/widgets/product_tile.dart';
import 'package:matjar_app/features/products/presentation/widgets/products_hero.dart';
import 'package:matjar_app/features/products/presentation/widgets/products_section_header.dart';

/// Home: hero, features, category chips and the product grid.
class ProductsPage extends ConsumerStatefulWidget {
  const ProductsPage({super.key});

  /// The category the hero's call to action jumps to.
  static const String heroCategory = 'headphones';

  @override
  ConsumerState<ProductsPage> createState() => _ProductsPageState();
}

class _ProductsPageState extends ConsumerState<ProductsPage> {
  final _searchController = TextEditingController();
  final _productsSectionKey = GlobalKey();
  bool _isSearching = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _closeSearch() {
    _searchController.clear();
    ref.read(productsQueryProvider.notifier).setSearch('');
    setState(() => _isSearching = false);
  }

  void _addToCart(Product product) {
    final outcome = ref.read(cartProvider.notifier).add(product);
    final l10n = context.l10n;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(switch (outcome) {
            AddToCartOutcome.added => l10n.addedToCart(product.name),
            AddToCartOutcome.limitReached => l10n.maxQuantityReached,
          }),
          duration: const Duration(seconds: 2),
        ),
      );
  }

  Future<void> _pickSort() async {
    final sort = await showSortSheet(
      context,
      ref.read(productsQueryProvider).sort,
    );
    if (sort != null) {
      ref.read(productsQueryProvider.notifier).setSort(sort);
    }
  }

  /// Shows headphones (when the shop has them) and scrolls to the grid.
  void _browseHeadphones(List<String> categories) {
    if (categories.contains(ProductsPage.heroCategory)) {
      ref
          .read(productsQueryProvider.notifier)
          .selectCategory(ProductsPage.heroCategory);
    }
    final target = _productsSectionKey.currentContext;
    if (target != null) {
      unawaited(
        Scrollable.ensureVisible(
          target,
          duration: MediaQuery.disableAnimationsOf(context)
              ? Duration.zero
              : const Duration(milliseconds: 450),
          curve: Curves.easeOutCubic,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final state = ref.watch(productsViewModelProvider);
    final viewModel = ref.read(productsViewModelProvider.notifier);
    final scheme = Theme.of(context).colorScheme;

    final appBar = AppBar(
      title: _isSearching
          ? TextField(
              controller: _searchController,
              autofocus: true,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(hintText: l10n.searchHint),
              onChanged: ref.read(productsQueryProvider.notifier).setSearch,
            )
          : Text(
              l10n.appTitle,
              style: AppTypography.wordmark(scheme.onSurface),
            ),
      actions: [
        if (_isSearching)
          IconButton(
            tooltip: l10n.closeSearch,
            onPressed: _closeSearch,
            icon: const Icon(Icons.close),
          )
        else ...[
          IconButton(
            tooltip: l10n.searchTooltip,
            onPressed: () => setState(() => _isSearching = true),
            icon: const Icon(Icons.search),
          ),
          const MyOrdersButton(),
          const LanguageToggleButton(),
        ],
        const CartBadgeButton(),
        const SizedBox(width: AppSpacing.xs),
      ],
    );

    return Scaffold(
      appBar: appBar,
      body: switch (state) {
        ProductsFailed(:final failure) => FailureView(
          failure: failure,
          onRetry: viewModel.load,
        ),
        ProductsLoading() => _Home(
          products: null,
          sectionKey: _productsSectionKey,
          onAddToCart: _addToCart,
          onPickSort: _pickSort,
          onBrowse: _browseHeadphones,
        ),
        ProductsLoaded(:final products) => RefreshIndicator.adaptive(
          onRefresh: viewModel.load,
          child: _Home(
            products: products,
            sectionKey: _productsSectionKey,
            onAddToCart: _addToCart,
            onPickSort: _pickSort,
            onBrowse: _browseHeadphones,
          ),
        ),
      },
    );
  }
}

class _Home extends ConsumerWidget {
  const _Home({
    required this.products,
    required this.sectionKey,
    required this.onAddToCart,
    required this.onPickSort,
    required this.onBrowse,
  });

  /// Null while loading.
  final List<Product>? products;
  final GlobalKey sectionKey;
  final ValueChanged<Product> onAddToCart;
  final VoidCallback onPickSort;
  final ValueChanged<List<String>> onBrowse;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    final query = ref.watch(productsQueryProvider);
    final all = products;
    final categories = all == null ? const <String>[] : categoriesOf(all);
    final activeCategory = categories.contains(query.category)
        ? query.category
        : null;
    final featured = all == null || all.isEmpty
        ? null
        : all.firstWhere(
            (product) => product.category == ProductsPage.heroCategory,
            orElse: () => all.first,
          );

    final Widget grid;
    if (all == null) {
      grid = const SliverProductGridSkeleton();
    } else if (all.isEmpty) {
      grid = SliverToBoxAdapter(
        child: StateMessage(
          icon: Icons.headphones_outlined,
          title: l10n.productsEmptyTitle,
          body: l10n.productsEmptyBody,
        ),
      );
    } else {
      final visible = applyProductsQuery(all, query);
      grid = visible.isEmpty
          ? SliverToBoxAdapter(
              child: StateMessage(
                icon: Icons.search_off,
                title: l10n.noResultsTitle,
                body: l10n.noResultsBody,
              ),
            )
          : SliverProductGrid(
              itemCount: visible.length,
              itemBuilder: (context, index) {
                final product = visible[index];
                return ProductTile(
                  key: ValueKey(product.slug),
                  product: product,
                  onAddToCart: () => onAddToCart(product),
                  onOpenDetails: () => showProductDetailsSheet(
                    context,
                    product: product,
                    onAddToCart: () => onAddToCart(product),
                  ),
                );
              },
            );
    }

    Widget boxed(Widget child) => SliverToBoxAdapter(
      child: ContentWidth(maxWidth: AppSpacing.maxContentWidth, child: child),
    );

    return CustomScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      slivers: [
        boxed(
          Padding(
            padding: const EdgeInsetsDirectional.fromSTEB(
              AppSpacing.page,
              AppSpacing.sm,
              AppSpacing.page,
              0,
            ),
            child: ProductsHero(
              featured: featured,
              onBrowse: () => onBrowse(categories),
            ),
          ),
        ),
        const SliverToBoxAdapter(child: SizedBox(height: AppSpacing.xxxl)),
        boxed(const FeaturesRow()),
        const SliverToBoxAdapter(child: SizedBox(height: AppSpacing.xxxl)),
        boxed(
          KeyedSubtree(
            key: sectionKey,
            child: ProductsSectionHeader(
              title: activeCategory == null
                  ? l10n.allProducts
                  : categoryLabel(l10n, activeCategory),
              onSort: onPickSort,
            ),
          ),
        ),
        boxed(
          CategoryChips(
            categories: categories,
            selected: activeCategory,
            onSelected: ref.read(productsQueryProvider.notifier).selectCategory,
          ),
        ),
        const SliverToBoxAdapter(child: SizedBox(height: AppSpacing.md)),
        SliverContentWidth(maxWidth: AppSpacing.maxContentWidth, sliver: grid),
      ],
    );
  }
}
