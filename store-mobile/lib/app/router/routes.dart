import 'package:matjar_app/features/orders/presentation/view_models/order_result_view_model.dart';

/// Route paths. Widgets navigate with these, never with string literals.
abstract final class Routes {
  static const String products = '/';
  static const String cart = '/cart';
  static const String payment = '/payment';
  static const String myOrders = '/orders';

  static const String referenceParam = 'reference';
  static const String originParam = 'origin';

  static String orderResult(
    String reference, {
    PaymentOrigin origin = PaymentOrigin.history,
  }) => Uri(
    path: '$myOrders/${Uri.encodeComponent(reference)}',
    queryParameters: {originParam: origin.name},
  ).toString();
}
