import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:matjar_app/core/di/core_providers.dart';
import 'package:matjar_app/core/error/result.dart';
import 'package:matjar_app/core/network/dio_failure_mapper.dart';
import 'package:matjar_app/core/network/loopback_url_rewriter.dart';
import 'package:matjar_app/features/products/data/models/product.dart';

final productsRepositoryProvider = Provider<ProductsRepository>(
  (ref) => ProductsRepository(
    dio: ref.watch(dioProvider),
    urlRewriter: ref.watch(loopbackUrlRewriterProvider),
  ),
);

/// Reads the catalogue from Matjar.
class ProductsRepository {
  const ProductsRepository({required this._dio, required this._urlRewriter});

  final Dio _dio;
  final LoopbackUrlRewriter _urlRewriter;

  Future<Result<List<Product>>> fetchProducts() async {
    try {
      final response = await _dio.get<Map<String, Object?>>('/api/products');
      final body = response.data;
      if (body case {'data': final List<Object?> items}) {
        return Success([
          for (final item in items)
            Product.fromJson(
              item is Map<String, Object?>
                  ? item
                  : throw FormatException('Invalid product: $item'),
              rewriteUrl: _urlRewriter.rewrite,
            ),
        ]);
      }
      throw FormatException('Missing data array: $body');
    } on Object catch (error) {
      return Err(mapErrorToFailure(error));
    }
  }
}
