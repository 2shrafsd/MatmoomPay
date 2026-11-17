import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:matjar_app/core/error/failure.dart';
import 'package:matjar_app/core/error/result.dart';
import 'package:matjar_app/features/products/data/models/product.dart';
import 'package:matjar_app/features/products/data/products_repository.dart';

@immutable
sealed class ProductsState {
  const ProductsState();
}

final class ProductsLoading extends ProductsState {
  const ProductsLoading();
}

final class ProductsLoaded extends ProductsState {
  const ProductsLoaded(this.products);

  final List<Product> products;
}

final class ProductsFailed extends ProductsState {
  const ProductsFailed(this.failure);

  final Failure failure;
}

final productsViewModelProvider =
    NotifierProvider<ProductsViewModel, ProductsState>(ProductsViewModel.new);

class ProductsViewModel extends Notifier<ProductsState> {
  @override
  ProductsState build() {
    unawaited(Future.microtask(load));
    return const ProductsLoading();
  }

  /// Loads the catalogue. On pull-to-refresh the current list stays on screen
  /// until the new one arrives.
  Future<void> load() async {
    if (state is! ProductsLoaded) {
      state = const ProductsLoading();
    }
    final result = await ref.read(productsRepositoryProvider).fetchProducts();
    if (!ref.mounted) {
      return;
    }
    state = switch (result) {
      Success(:final data) => ProductsLoaded(data),
      Err(:final failure) => ProductsFailed(failure),
    };
  }
}
