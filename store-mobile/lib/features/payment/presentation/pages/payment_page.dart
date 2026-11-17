import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/app/router/routes.dart';
import 'package:matjar_app/core/di/core_providers.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';
import 'package:matjar_app/core/widgets/primary_button.dart';
import 'package:matjar_app/core/widgets/state_message.dart';
import 'package:matjar_app/features/orders/data/models/created_order.dart';
import 'package:matjar_app/features/orders/presentation/view_models/order_result_view_model.dart';
import 'package:matjar_app/features/payment/domain/payment_return_matcher.dart';
import 'package:webview_flutter/webview_flutter.dart';

/// Shows MatmoomPay's hosted checkout page.
///
/// The shopper types card details on the gateway's page, not in the app.
/// When the gateway redirects to Matjar's success or cancel URL, the WebView
/// stops there and the app opens the result screen, which asks Matjar for
/// the real status.
class PaymentPage extends ConsumerStatefulWidget {
  const PaymentPage({required this.order, super.key});

  final CreatedOrder order;

  @override
  ConsumerState<PaymentPage> createState() => _PaymentPageState();
}

class _PaymentPageState extends ConsumerState<PaymentPage> {
  late final PaymentReturnMatcher _matcher;
  late final WebViewController _controller;
  int _progress = 0;
  bool _loadFailed = false;
  bool _finished = false;

  @override
  void initState() {
    super.initState();
    _matcher = PaymentReturnMatcher(
      successUrlPrefix: widget.order.successUrlPrefix,
      cancelUrlPrefix: widget.order.cancelUrlPrefix,
      urlRewriter: ref.read(loopbackUrlRewriterProvider),
    );
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (request) {
            if (_handleReturn(request.url)) {
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
          // Some redirects (e.g. after a form POST on Android) skip
          // onNavigationRequest, so the URL is checked again here.
          onUrlChange: (change) => _handleReturn(change.url),
          onPageStarted: _handleReturn,
          onProgress: (progress) {
            if (mounted) {
              setState(() => _progress = progress);
            }
          },
          onWebResourceError: (error) {
            if (_handleReturn(error.url)) {
              return;
            }
            if ((error.isForMainFrame ?? true) && mounted) {
              setState(() => _loadFailed = true);
            }
          },
        ),
      );
    _loadPaymentPage();
  }

  void _loadPaymentPage() =>
      unawaited(_controller.loadRequest(Uri.parse(widget.order.paymentUrl)));

  void _reload() {
    setState(() {
      _loadFailed = false;
      _progress = 0;
    });
    _loadPaymentPage();
  }

  /// Returns true when [url] is one of Matjar's return pages.
  bool _handleReturn(String? url) {
    if (url == null) {
      return false;
    }
    final kind = _matcher.match(url);
    if (kind == null) {
      return false;
    }
    _finish(switch (kind) {
      PaymentReturn.success => PaymentOrigin.returnedSuccess,
      PaymentReturn.cancel => PaymentOrigin.returnedCancel,
    });
    return true;
  }

  void _finish(PaymentOrigin origin) {
    if (_finished || !mounted) {
      return;
    }
    _finished = true;
    context.pushReplacement(
      Routes.orderResult(widget.order.reference, origin: origin),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          _finish(PaymentOrigin.closedByUser);
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(l10n.paymentTitle),
          automaticallyImplyLeading: false,
          leading: IconButton(
            tooltip: l10n.closePayment,
            onPressed: () => _finish(PaymentOrigin.closedByUser),
            icon: const Icon(Icons.close),
          ),
          bottom: PreferredSize(
            preferredSize: const Size.fromHeight(AppSizes.progressBarHeight),
            child: _progress < 100 && !_loadFailed
                ? LinearProgressIndicator(
                    value: _progress == 0 ? null : _progress / 100,
                    minHeight: AppSizes.progressBarHeight,
                  )
                : const SizedBox(height: AppSizes.progressBarHeight),
          ),
        ),
        body: _loadFailed
            ? StateMessage(
                icon: Icons.cloud_off_outlined,
                iconColor: Theme.of(context).colorScheme.error,
                title: l10n.paymentLoadFailed,
                actions: [
                  PrimaryButton(label: l10n.reload, onPressed: _reload),
                ],
              )
            : WebViewWidget(controller: _controller),
      ),
    );
  }
}
