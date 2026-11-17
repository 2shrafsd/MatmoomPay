import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:matjar_app/app/router/routes.dart';
import 'package:matjar_app/features/cart/presentation/pages/cart_page.dart';
import 'package:matjar_app/features/orders/data/models/created_order.dart';
import 'package:matjar_app/features/orders/presentation/pages/my_orders_page.dart';
import 'package:matjar_app/features/orders/presentation/pages/order_result_page.dart';
import 'package:matjar_app/features/orders/presentation/view_models/order_result_view_model.dart';
import 'package:matjar_app/features/payment/presentation/pages/payment_page.dart';
import 'package:matjar_app/features/products/presentation/pages/products_page.dart';

final appRouterProvider = Provider<GoRouter>((ref) {
  final router = GoRouter(
    initialLocation: Routes.products,
    routes: [
      GoRoute(
        path: Routes.products,
        builder: (_, _) => const ProductsPage(),
        routes: [
          GoRoute(
            path: Routes.cart.substring(1),
            builder: (_, _) => const CartPage(),
          ),
          GoRoute(
            path: Routes.payment.substring(1),
            // The created order travels in memory only; a cold deep link
            // without it goes back to the cart.
            redirect: (_, state) =>
                state.extra is CreatedOrder ? null : Routes.cart,
            builder: (_, state) =>
                PaymentPage(order: state.extra! as CreatedOrder),
          ),
          GoRoute(
            path: Routes.myOrders.substring(1),
            builder: (_, _) => const MyOrdersPage(),
            routes: [
              GoRoute(
                path: ':${Routes.referenceParam}',
                builder: (_, state) => OrderResultPage(
                  args: (
                    reference: state.pathParameters[Routes.referenceParam]!,
                    origin: PaymentOrigin.fromName(
                      state.uri.queryParameters[Routes.originParam],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    ],
  );
  ref.onDispose(router.dispose);
  return router;
});
