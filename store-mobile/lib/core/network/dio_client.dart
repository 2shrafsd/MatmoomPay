import 'dart:developer' as developer;

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:matjar_app/core/config/app_config.dart';

/// Builds the single [Dio] instance the app talks to Matjar with.
Dio createDio(AppConfig config) {
  final dio = Dio(
    BaseOptions(
      baseUrl: config.apiBaseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 20),
      sendTimeout: const Duration(seconds: 20),
      headers: const {'Accept': 'application/json'},
    ),
  );
  if (kDebugMode) {
    dio.interceptors.add(const _RequestLogInterceptor());
  }
  return dio;
}

/// Logs method, path and status only. Headers carry the order token and the
/// body carries the customer's phone number, so neither is logged.
class _RequestLogInterceptor extends Interceptor {
  const _RequestLogInterceptor();

  @override
  void onResponse(
    Response<dynamic> response,
    ResponseInterceptorHandler handler,
  ) {
    developer.log(
      '${response.requestOptions.method} ${response.requestOptions.path} '
      '-> ${response.statusCode}',
      name: 'api',
    );
    handler.next(response);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    developer.log(
      '${err.requestOptions.method} ${err.requestOptions.path} '
      '-> ${err.response?.statusCode ?? err.type.name}',
      name: 'api',
    );
    handler.next(err);
  }
}
