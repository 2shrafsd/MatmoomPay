import 'package:flutter_test/flutter_test.dart';
import 'package:matjar_app/core/error/failure.dart';
import 'package:matjar_app/core/error/result.dart';
import 'package:matjar_app/core/network/loopback_url_rewriter.dart';
import 'package:matjar_app/features/products/data/models/product.dart';
import 'package:matjar_app/features/products/data/products_repository.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/fixtures.dart';

void main() {
  late FakeHttpAdapter adapter;

  ProductsRepository repositoryWith(FakeHandler handler) {
    adapter = FakeHttpAdapter(handler);
    return ProductsRepository(
      dio: fakeDio(adapter),
      urlRewriter: const LoopbackUrlRewriter(targetHost: '10.0.2.2'),
    );
  }

  test('parses products and rewrites localhost image URLs', () async {
    final repository = repositoryWith(
      (_) => FakeReply(200, {
        'data': [
          productJson(),
          productJson(slug: 'pro-earbuds', imageUrl: null),
        ],
      }),
    );

    final result = await repository.fetchProducts();

    expect(adapter.requests.single.path, '/api/products');
    final products = (result as Success<List<Product>>).data;
    expect(products, hasLength(2));
    expect(products.first.slug, 'studio-headphones');
    expect(products.first.price, 12000);
    expect(products.first.colorHex, '#9F1239');
    expect(
      products.first.imageUrl,
      'http://10.0.2.2:8001/images/studio-headphones.jpg',
    );
    expect(products.last.imageUrl, isNull);
  });

  test('an empty catalogue is a success with no products', () async {
    final repository = repositoryWith(
      (_) => const FakeReply(200, {'data': <Object?>[]}),
    );
    final result = await repository.fetchProducts();
    expect((result as Success<List<Product>>).data, isEmpty);
  });

  test('a price that is not an integer is a parsing failure', () async {
    final repository = repositoryWith(
      (_) => FakeReply(200, {
        'data': [productJson(price: '12000')],
      }),
    );
    final result = await repository.fetchProducts();
    expect((result as Err<List<Product>>).failure, isA<ParsingFailure>());
  });

  test('a body without "data" is a parsing failure', () async {
    final repository = repositoryWith(
      (_) => const FakeReply(200, {'items': <Object?>[]}),
    );
    final result = await repository.fetchProducts();
    expect((result as Err<List<Product>>).failure, isA<ParsingFailure>());
  });

  test('no connection maps to NetworkFailure', () async {
    adapter = FakeHttpAdapter.offline();
    final repository = ProductsRepository(
      dio: fakeDio(adapter),
      urlRewriter: const LoopbackUrlRewriter(targetHost: '10.0.2.2'),
    );
    final result = await repository.fetchProducts();
    expect((result as Err<List<Product>>).failure, isA<NetworkFailure>());
  });

  test('a 500 maps to ServerFailure with its status code', () async {
    final repository = repositoryWith(
      (_) => const FakeReply(500, {'message': 'Server Error'}),
    );
    final result = await repository.fetchProducts();
    final failure = (result as Err<List<Product>>).failure;
    expect(failure, isA<ServerFailure>());
    expect((failure as ServerFailure).statusCode, 500);
  });
}
