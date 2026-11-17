// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appTitle => 'Matjar';

  @override
  String get heroEyebrow => 'Wireless headphones';

  @override
  String get heroHeadline => 'Clean sound,\nclear calls,\nall workday.';

  @override
  String get heroBody =>
      'Headphones and speakers for desk and home, priced in Sudanese pounds and delivered to your door.';

  @override
  String get heroCta => 'Browse headphones';

  @override
  String get featuresEyebrow => 'Features';

  @override
  String get featureBatteryTitle => 'Battery for\nthe whole day';

  @override
  String get featureBatteryBody =>
      'One charge lasts a full workday, and quick charging gives you hours in minutes.';

  @override
  String get featureMicTitle => 'Clear\ncalls';

  @override
  String get featureMicBody =>
      'Microphones that tone down room noise so people hear you clearly.';

  @override
  String get featureComfortTitle => 'Comfortable\nfor hours';

  @override
  String get featureComfortBody =>
      'Soft cushions and a light build for long work and study sessions.';

  @override
  String get productsEyebrow => 'Products';

  @override
  String get categoryHeadphones => 'Headphones';

  @override
  String get categoryEarbuds => 'Earbuds';

  @override
  String get categorySpeakers => 'Speakers';

  @override
  String get categoryAccessories => 'Accessories';

  @override
  String get allCategories => 'All';

  @override
  String get allProducts => 'All products';

  @override
  String get sort => 'Sort';

  @override
  String get sortNewest => 'Newest';

  @override
  String get sortPriceLowToHigh => 'Price: low to high';

  @override
  String get sortPriceHighToLow => 'Price: high to low';

  @override
  String get searchTooltip => 'Search';

  @override
  String get searchHint => 'Search by product name';

  @override
  String get closeSearch => 'Close search';

  @override
  String get noResultsTitle => 'No products match';

  @override
  String get noResultsBody => 'Try another word or pick «All».';

  @override
  String get productDetailsHint => 'Show details';

  @override
  String get testCardHint =>
      'Testing: card 4242 4242 4242 4242, any future expiry, any CVC, code 1234.';

  @override
  String get addToCart => 'Add to cart';

  @override
  String addedToCart(String name) {
    return '$name added to your cart';
  }

  @override
  String get maxQuantityReached => 'You can add up to 10 of one product';

  @override
  String get cartTooltip => 'Cart';

  @override
  String cartItemCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count items in cart',
      one: '1 item in cart',
      zero: 'Cart is empty',
    );
    return '$_temp0';
  }

  @override
  String get myOrdersTitle => 'My orders';

  @override
  String get languageToggle => 'ع';

  @override
  String get changeLanguageTooltip => 'Change language';

  @override
  String get productsEmptyTitle => 'No products yet';

  @override
  String get productsEmptyBody =>
      'The shop hasn\'t listed anything. Pull down to refresh.';

  @override
  String get retry => 'Try again';

  @override
  String get errorNetwork =>
      'Couldn\'t reach the shop. Check your connection and that the Matjar server is running, then try again.';

  @override
  String get errorGatewayUnavailable =>
      'The payment gateway isn\'t responding. Wait a moment and try again.';

  @override
  String get errorThrottled =>
      'Too many requests in a short time. Wait a minute and try again.';

  @override
  String errorServer(String code) {
    return 'The shop server had a problem (code $code). Try again shortly.';
  }

  @override
  String get errorNotFound => 'We couldn\'t find this order.';

  @override
  String get errorParsing =>
      'The shop sent a response we couldn\'t read. Update the app or tell the shop team.';

  @override
  String get errorUnknown => 'Something unexpected happened. Try again.';

  @override
  String get errorValidation => 'Check the details you entered and try again.';

  @override
  String amountSdg(String amount) {
    return 'SDG $amount';
  }

  @override
  String get cartTitle => 'Cart';

  @override
  String get cartEmptyTitle => 'Your cart is empty';

  @override
  String get cartEmptyBody =>
      'Add a product from the products page, then come back here to pay.';

  @override
  String get browseProducts => 'Browse products';

  @override
  String get increaseQuantity => 'Increase quantity';

  @override
  String get decreaseQuantity => 'Decrease quantity';

  @override
  String get removeItem => 'Remove from cart';

  @override
  String quantityValue(int quantity) {
    return 'Quantity $quantity';
  }

  @override
  String get cartTotal => 'Total';

  @override
  String get customerDetailsTitle => 'Your details';

  @override
  String get customerNameLabel => 'Name';

  @override
  String get customerPhoneLabel => 'Phone number';

  @override
  String get customerPhoneHint => '+249 912 345 678';

  @override
  String get nameRequired => 'Enter your name';

  @override
  String get nameTooLong => 'Name is longer than 80 characters';

  @override
  String get phoneRequired => 'Enter your phone number';

  @override
  String get phoneInvalid => 'Use 8 to 16 digits; you can start with +';

  @override
  String payAmount(String amount) {
    return 'Pay $amount';
  }

  @override
  String get paymentSecureNote =>
      'You enter card details on the MatmoomPay page. Neither the app nor the shop sees them.';

  @override
  String get paymentTitle => 'Payment';

  @override
  String get closePayment => 'Close payment page';

  @override
  String get paymentLoadFailed =>
      'Couldn\'t open the payment page. Check your connection and that the MatmoomPay gateway is running.';

  @override
  String get reload => 'Reload';

  @override
  String get orderResultTitle => 'Order status';

  @override
  String get statusPaidTitle => 'Payment received';

  @override
  String statusPaidBody(String amount) {
    return 'We received $amount. We\'ll call you to arrange delivery.';
  }

  @override
  String get orderReference => 'Order number';

  @override
  String get invoiceNumber => 'Invoice number';

  @override
  String get paidAt => 'Paid at';

  @override
  String get orderTotal => 'Amount';

  @override
  String get statusConfirmingTitle => 'Confirming your payment';

  @override
  String get statusConfirmingBody =>
      'Waiting for the gateway to confirm. This usually takes a few seconds.';

  @override
  String get statusPendingTitle => 'Payment not confirmed yet';

  @override
  String get statusPendingBody =>
      'If your card was charged, the order will show as paid within a few minutes. Check again shortly.';

  @override
  String get checkAgain => 'Check again';

  @override
  String get statusFailedTitle => 'Payment didn\'t go through';

  @override
  String get statusFailedBody =>
      'The gateway declined the payment and nothing was charged. Try again or use another card.';

  @override
  String get statusCancelledTitle => 'You cancelled the payment';

  @override
  String get statusCancelledBody =>
      'Nothing was charged, and your items are still in the cart.';

  @override
  String get statusExpiredTitle => 'Payment timed out';

  @override
  String get statusExpiredBody =>
      'The payment page expired before you finished. Go back to the cart and start again.';

  @override
  String get statusAbandonedTitle => 'Payment not completed';

  @override
  String get statusAbandonedBody =>
      'The payment page was closed before you finished. Go back to the cart and start again.';

  @override
  String get tryAgain => 'Try again';

  @override
  String get continueShopping => 'Continue shopping';

  @override
  String get statusLabelPending => 'Awaiting payment';

  @override
  String get statusLabelPaid => 'Paid';

  @override
  String get statusLabelFailed => 'Failed';

  @override
  String get statusLabelCancelled => 'Cancelled';

  @override
  String get statusLabelExpired => 'Expired';

  @override
  String get statusLabelAbandoned => 'Not completed';

  @override
  String get statusLabelUnknown => 'Unknown status';

  @override
  String get myOrdersEmptyTitle => 'No orders on this device';

  @override
  String get myOrdersEmptyBody =>
      'Orders you place from this app show up here with their payment status.';

  @override
  String get orderStatusUnavailable => 'Couldn\'t refresh the status';

  @override
  String orderItemsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count products',
      one: '1 product',
    );
    return '$_temp0';
  }
}
