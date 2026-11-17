import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

/// What the fake server answers for one request.
class FakeReply {
  const FakeReply(this.statusCode, [this.body]);

  final int statusCode;
  final Object? body;
}

typedef FakeHandler = FakeReply Function(RequestOptions options);

/// In-memory replacement for Dio's HTTP adapter. Records every request so
/// tests can assert on paths, headers and bodies.
class FakeHttpAdapter implements HttpClientAdapter {
  FakeHttpAdapter(this.handler);

  /// Answers every request with a connection error.
  factory FakeHttpAdapter.offline() => FakeHttpAdapter(
    (options) => throw DioException.connectionError(
      requestOptions: options,
      reason: 'offline',
    ),
  );

  final FakeHandler handler;
  final List<RequestOptions> requests = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    final reply = handler(options);
    return ResponseBody.fromString(
      reply.body == null ? '' : jsonEncode(reply.body),
      reply.statusCode,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

Dio fakeDio(
  FakeHttpAdapter adapter, {
  String baseUrl = 'http://10.0.2.2:8001',
}) {
  return Dio(BaseOptions(baseUrl: baseUrl))..httpClientAdapter = adapter;
}
