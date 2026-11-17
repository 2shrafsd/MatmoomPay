import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_en.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'generated/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ar'),
    Locale('en'),
  ];

  /// Shop name shown in the app bar and task switcher
  ///
  /// In ar, this message translates to:
  /// **'متجر'**
  String get appTitle;

  /// No description provided for @heroEyebrow.
  ///
  /// In ar, this message translates to:
  /// **'سماعات لاسلكية'**
  String get heroEyebrow;

  /// Hero headline, exactly three lines
  ///
  /// In ar, this message translates to:
  /// **'صوت نقي\nومكالمات واضحة\nطوال يوم العمل.'**
  String get heroHeadline;

  /// No description provided for @heroBody.
  ///
  /// In ar, this message translates to:
  /// **'سماعات ومكبرات صوت للمكتب والبيت، بأسعار بالجنيه السوداني وتوصيل إلى الباب.'**
  String get heroBody;

  /// No description provided for @heroCta.
  ///
  /// In ar, this message translates to:
  /// **'تصفّح السماعات'**
  String get heroCta;

  /// No description provided for @featuresEyebrow.
  ///
  /// In ar, this message translates to:
  /// **'المزايا'**
  String get featuresEyebrow;

  /// No description provided for @featureBatteryTitle.
  ///
  /// In ar, this message translates to:
  /// **'بطارية تكفي\nيوم العمل'**
  String get featureBatteryTitle;

  /// No description provided for @featureBatteryBody.
  ///
  /// In ar, this message translates to:
  /// **'شحنة واحدة تكفي يوم عمل كامل، والشحن السريع يعطيك ساعات في دقائق.'**
  String get featureBatteryBody;

  /// No description provided for @featureMicTitle.
  ///
  /// In ar, this message translates to:
  /// **'مكالمات\nواضحة'**
  String get featureMicTitle;

  /// No description provided for @featureMicBody.
  ///
  /// In ar, this message translates to:
  /// **'ميكروفونات تخفف ضجيج المكان فيسمعك الطرف الآخر بوضوح.'**
  String get featureMicBody;

  /// No description provided for @featureComfortTitle.
  ///
  /// In ar, this message translates to:
  /// **'مريحة\nلساعات'**
  String get featureComfortTitle;

  /// No description provided for @featureComfortBody.
  ///
  /// In ar, this message translates to:
  /// **'وسائد ناعمة ووزن خفيف لجلسات العمل والدراسة الطويلة.'**
  String get featureComfortBody;

  /// No description provided for @productsEyebrow.
  ///
  /// In ar, this message translates to:
  /// **'المنتجات'**
  String get productsEyebrow;

  /// No description provided for @categoryHeadphones.
  ///
  /// In ar, this message translates to:
  /// **'سماعات رأس'**
  String get categoryHeadphones;

  /// No description provided for @categoryEarbuds.
  ///
  /// In ar, this message translates to:
  /// **'سماعات أذن'**
  String get categoryEarbuds;

  /// No description provided for @categorySpeakers.
  ///
  /// In ar, this message translates to:
  /// **'مكبرات صوت'**
  String get categorySpeakers;

  /// No description provided for @categoryAccessories.
  ///
  /// In ar, this message translates to:
  /// **'إكسسوارات'**
  String get categoryAccessories;

  /// No description provided for @allCategories.
  ///
  /// In ar, this message translates to:
  /// **'الكل'**
  String get allCategories;

  /// No description provided for @allProducts.
  ///
  /// In ar, this message translates to:
  /// **'كل المنتجات'**
  String get allProducts;

  /// No description provided for @sort.
  ///
  /// In ar, this message translates to:
  /// **'الترتيب'**
  String get sort;

  /// No description provided for @sortNewest.
  ///
  /// In ar, this message translates to:
  /// **'الأحدث'**
  String get sortNewest;

  /// No description provided for @sortPriceLowToHigh.
  ///
  /// In ar, this message translates to:
  /// **'السعر من الأقل'**
  String get sortPriceLowToHigh;

  /// No description provided for @sortPriceHighToLow.
  ///
  /// In ar, this message translates to:
  /// **'السعر من الأعلى'**
  String get sortPriceHighToLow;

  /// No description provided for @searchTooltip.
  ///
  /// In ar, this message translates to:
  /// **'بحث'**
  String get searchTooltip;

  /// No description provided for @searchHint.
  ///
  /// In ar, this message translates to:
  /// **'ابحث باسم المنتج'**
  String get searchHint;

  /// No description provided for @closeSearch.
  ///
  /// In ar, this message translates to:
  /// **'أغلق البحث'**
  String get closeSearch;

  /// No description provided for @noResultsTitle.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد منتجات بهذا الاسم'**
  String get noResultsTitle;

  /// No description provided for @noResultsBody.
  ///
  /// In ar, this message translates to:
  /// **'جرّب كلمة أخرى أو اختر «الكل».'**
  String get noResultsBody;

  /// No description provided for @productDetailsHint.
  ///
  /// In ar, this message translates to:
  /// **'اعرض التفاصيل'**
  String get productDetailsHint;

  /// Reminder of the MatmoomPay sandbox card, shown on the cart screen
  ///
  /// In ar, this message translates to:
  /// **'للتجربة: البطاقة 4242 4242 4242 4242، أي تاريخ انتهاء قادم، أي CVC، ورمز التحقق 1234.'**
  String get testCardHint;

  /// No description provided for @addToCart.
  ///
  /// In ar, this message translates to:
  /// **'أضف للسلة'**
  String get addToCart;

  /// No description provided for @addedToCart.
  ///
  /// In ar, this message translates to:
  /// **'أضفنا {name} إلى السلة'**
  String addedToCart(String name);

  /// No description provided for @maxQuantityReached.
  ///
  /// In ar, this message translates to:
  /// **'الحد الأعلى 10 قطع من المنتج الواحد'**
  String get maxQuantityReached;

  /// No description provided for @cartTooltip.
  ///
  /// In ar, this message translates to:
  /// **'السلة'**
  String get cartTooltip;

  /// Screen-reader label for the cart button
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =0{السلة فارغة} =1{في السلة قطعة واحدة} =2{في السلة قطعتان} few{في السلة {count} قطع} many{في السلة {count} قطعة} other{في السلة {count} قطعة}}'**
  String cartItemCount(int count);

  /// No description provided for @myOrdersTitle.
  ///
  /// In ar, this message translates to:
  /// **'طلباتي'**
  String get myOrdersTitle;

  /// Name of the OTHER language, shown on the switch button
  ///
  /// In ar, this message translates to:
  /// **'EN'**
  String get languageToggle;

  /// No description provided for @changeLanguageTooltip.
  ///
  /// In ar, this message translates to:
  /// **'غيّر اللغة'**
  String get changeLanguageTooltip;

  /// No description provided for @productsEmptyTitle.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد منتجات حاليًا'**
  String get productsEmptyTitle;

  /// No description provided for @productsEmptyBody.
  ///
  /// In ar, this message translates to:
  /// **'لم يضف المتجر منتجات بعد. اسحب الشاشة للأسفل لتحديثها.'**
  String get productsEmptyBody;

  /// No description provided for @retry.
  ///
  /// In ar, this message translates to:
  /// **'حاول مرة أخرى'**
  String get retry;

  /// No description provided for @errorNetwork.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر الاتصال بالمتجر. تأكد من الإنترنت وأن خادم متجر يعمل، ثم حاول مرة أخرى.'**
  String get errorNetwork;

  /// No description provided for @errorGatewayUnavailable.
  ///
  /// In ar, this message translates to:
  /// **'بوابة الدفع لا تستجيب الآن. انتظر قليلًا وحاول مرة أخرى.'**
  String get errorGatewayUnavailable;

  /// No description provided for @errorThrottled.
  ///
  /// In ar, this message translates to:
  /// **'طلبات كثيرة في وقت قصير. انتظر دقيقة ثم حاول مرة أخرى.'**
  String get errorThrottled;

  /// No description provided for @errorServer.
  ///
  /// In ar, this message translates to:
  /// **'حدث خلل في خادم المتجر (رمز {code}). حاول بعد قليل.'**
  String errorServer(String code);

  /// No description provided for @errorNotFound.
  ///
  /// In ar, this message translates to:
  /// **'لم نجد هذا الطلب.'**
  String get errorNotFound;

  /// No description provided for @errorParsing.
  ///
  /// In ar, this message translates to:
  /// **'وصلنا رد غير مفهوم من المتجر. حدّث التطبيق أو أبلغ فريق المتجر.'**
  String get errorParsing;

  /// No description provided for @errorUnknown.
  ///
  /// In ar, this message translates to:
  /// **'حدث شيء لم نتوقعه. حاول مرة أخرى.'**
  String get errorUnknown;

  /// No description provided for @errorValidation.
  ///
  /// In ar, this message translates to:
  /// **'راجع البيانات المكتوبة ثم حاول مرة أخرى.'**
  String get errorValidation;

  /// Whole Sudanese pounds
  ///
  /// In ar, this message translates to:
  /// **'{amount} ج.س'**
  String amountSdg(String amount);

  /// No description provided for @cartTitle.
  ///
  /// In ar, this message translates to:
  /// **'السلة'**
  String get cartTitle;

  /// No description provided for @cartEmptyTitle.
  ///
  /// In ar, this message translates to:
  /// **'سلتك فارغة'**
  String get cartEmptyTitle;

  /// No description provided for @cartEmptyBody.
  ///
  /// In ar, this message translates to:
  /// **'أضف منتجًا من صفحة المنتجات ثم ارجع هنا للدفع.'**
  String get cartEmptyBody;

  /// No description provided for @browseProducts.
  ///
  /// In ar, this message translates to:
  /// **'تصفّح المنتجات'**
  String get browseProducts;

  /// No description provided for @increaseQuantity.
  ///
  /// In ar, this message translates to:
  /// **'زيادة الكمية'**
  String get increaseQuantity;

  /// No description provided for @decreaseQuantity.
  ///
  /// In ar, this message translates to:
  /// **'إنقاص الكمية'**
  String get decreaseQuantity;

  /// No description provided for @removeItem.
  ///
  /// In ar, this message translates to:
  /// **'احذف من السلة'**
  String get removeItem;

  /// No description provided for @quantityValue.
  ///
  /// In ar, this message translates to:
  /// **'الكمية {quantity}'**
  String quantityValue(int quantity);

  /// No description provided for @cartTotal.
  ///
  /// In ar, this message translates to:
  /// **'الإجمالي'**
  String get cartTotal;

  /// No description provided for @customerDetailsTitle.
  ///
  /// In ar, this message translates to:
  /// **'بياناتك'**
  String get customerDetailsTitle;

  /// No description provided for @customerNameLabel.
  ///
  /// In ar, this message translates to:
  /// **'الاسم'**
  String get customerNameLabel;

  /// No description provided for @customerPhoneLabel.
  ///
  /// In ar, this message translates to:
  /// **'رقم الهاتف'**
  String get customerPhoneLabel;

  /// No description provided for @customerPhoneHint.
  ///
  /// In ar, this message translates to:
  /// **'+249 912 345 678'**
  String get customerPhoneHint;

  /// No description provided for @nameRequired.
  ///
  /// In ar, this message translates to:
  /// **'اكتب اسمك'**
  String get nameRequired;

  /// No description provided for @nameTooLong.
  ///
  /// In ar, this message translates to:
  /// **'الاسم أطول من 80 حرفًا'**
  String get nameTooLong;

  /// No description provided for @phoneRequired.
  ///
  /// In ar, this message translates to:
  /// **'اكتب رقم هاتفك'**
  String get phoneRequired;

  /// No description provided for @phoneInvalid.
  ///
  /// In ar, this message translates to:
  /// **'اكتب من 8 إلى 16 رقمًا، ويمكن أن يبدأ الرقم بـ +'**
  String get phoneInvalid;

  /// No description provided for @payAmount.
  ///
  /// In ar, this message translates to:
  /// **'ادفع {amount}'**
  String payAmount(String amount);

  /// No description provided for @paymentSecureNote.
  ///
  /// In ar, this message translates to:
  /// **'تكتب بيانات البطاقة في صفحة بوابة MatmoomPay، ولا يراها التطبيق ولا المتجر.'**
  String get paymentSecureNote;

  /// No description provided for @paymentTitle.
  ///
  /// In ar, this message translates to:
  /// **'الدفع'**
  String get paymentTitle;

  /// No description provided for @closePayment.
  ///
  /// In ar, this message translates to:
  /// **'أغلق صفحة الدفع'**
  String get closePayment;

  /// No description provided for @paymentLoadFailed.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر فتح صفحة الدفع. تأكد من الاتصال وأن بوابة MatmoomPay تعمل.'**
  String get paymentLoadFailed;

  /// No description provided for @reload.
  ///
  /// In ar, this message translates to:
  /// **'أعد التحميل'**
  String get reload;

  /// No description provided for @orderResultTitle.
  ///
  /// In ar, this message translates to:
  /// **'حالة الطلب'**
  String get orderResultTitle;

  /// No description provided for @statusPaidTitle.
  ///
  /// In ar, this message translates to:
  /// **'تم الدفع'**
  String get statusPaidTitle;

  /// No description provided for @statusPaidBody.
  ///
  /// In ar, this message translates to:
  /// **'وصلنا مبلغ {amount}. سنتصل بك على رقمك لترتيب التوصيل.'**
  String statusPaidBody(String amount);

  /// No description provided for @orderReference.
  ///
  /// In ar, this message translates to:
  /// **'رقم الطلب'**
  String get orderReference;

  /// No description provided for @invoiceNumber.
  ///
  /// In ar, this message translates to:
  /// **'رقم الفاتورة'**
  String get invoiceNumber;

  /// No description provided for @paidAt.
  ///
  /// In ar, this message translates to:
  /// **'وقت الدفع'**
  String get paidAt;

  /// No description provided for @orderTotal.
  ///
  /// In ar, this message translates to:
  /// **'المبلغ'**
  String get orderTotal;

  /// No description provided for @statusConfirmingTitle.
  ///
  /// In ar, this message translates to:
  /// **'نتأكد من الدفع'**
  String get statusConfirmingTitle;

  /// No description provided for @statusConfirmingBody.
  ///
  /// In ar, this message translates to:
  /// **'ننتظر تأكيد بوابة الدفع، ويأخذ هذا عادةً بضع ثوانٍ.'**
  String get statusConfirmingBody;

  /// No description provided for @statusPendingTitle.
  ///
  /// In ar, this message translates to:
  /// **'لم يصلنا تأكيد الدفع بعد'**
  String get statusPendingTitle;

  /// No description provided for @statusPendingBody.
  ///
  /// In ar, this message translates to:
  /// **'إذا خُصم المبلغ من بطاقتك فسيظهر الطلب مدفوعًا خلال دقائق. تحقق مرة أخرى بعد قليل.'**
  String get statusPendingBody;

  /// No description provided for @checkAgain.
  ///
  /// In ar, this message translates to:
  /// **'تحقق مرة أخرى'**
  String get checkAgain;

  /// No description provided for @statusFailedTitle.
  ///
  /// In ar, this message translates to:
  /// **'لم ينجح الدفع'**
  String get statusFailedTitle;

  /// No description provided for @statusFailedBody.
  ///
  /// In ar, this message translates to:
  /// **'رفضت البوابة العملية ولم يُخصم أي مبلغ. جرّب مرة أخرى أو استخدم بطاقة أخرى.'**
  String get statusFailedBody;

  /// No description provided for @statusCancelledTitle.
  ///
  /// In ar, this message translates to:
  /// **'ألغيت الدفع'**
  String get statusCancelledTitle;

  /// No description provided for @statusCancelledBody.
  ///
  /// In ar, this message translates to:
  /// **'لم يُخصم أي مبلغ، ومنتجاتك ما زالت في السلة.'**
  String get statusCancelledBody;

  /// No description provided for @statusExpiredTitle.
  ///
  /// In ar, this message translates to:
  /// **'انتهت مهلة الدفع'**
  String get statusExpiredTitle;

  /// No description provided for @statusExpiredBody.
  ///
  /// In ar, this message translates to:
  /// **'انتهت صلاحية صفحة الدفع قبل إتمامها. ارجع إلى السلة وابدأ من جديد.'**
  String get statusExpiredBody;

  /// No description provided for @statusAbandonedTitle.
  ///
  /// In ar, this message translates to:
  /// **'لم يكتمل الدفع'**
  String get statusAbandonedTitle;

  /// No description provided for @statusAbandonedBody.
  ///
  /// In ar, this message translates to:
  /// **'أُغلقت صفحة الدفع قبل إتمامها. ارجع إلى السلة وابدأ من جديد.'**
  String get statusAbandonedBody;

  /// No description provided for @tryAgain.
  ///
  /// In ar, this message translates to:
  /// **'جرّب مرة أخرى'**
  String get tryAgain;

  /// No description provided for @continueShopping.
  ///
  /// In ar, this message translates to:
  /// **'تابع التسوق'**
  String get continueShopping;

  /// No description provided for @statusLabelPending.
  ///
  /// In ar, this message translates to:
  /// **'بانتظار الدفع'**
  String get statusLabelPending;

  /// No description provided for @statusLabelPaid.
  ///
  /// In ar, this message translates to:
  /// **'مدفوع'**
  String get statusLabelPaid;

  /// No description provided for @statusLabelFailed.
  ///
  /// In ar, this message translates to:
  /// **'فشل الدفع'**
  String get statusLabelFailed;

  /// No description provided for @statusLabelCancelled.
  ///
  /// In ar, this message translates to:
  /// **'ملغي'**
  String get statusLabelCancelled;

  /// No description provided for @statusLabelExpired.
  ///
  /// In ar, this message translates to:
  /// **'منتهي'**
  String get statusLabelExpired;

  /// No description provided for @statusLabelAbandoned.
  ///
  /// In ar, this message translates to:
  /// **'غير مكتمل'**
  String get statusLabelAbandoned;

  /// No description provided for @statusLabelUnknown.
  ///
  /// In ar, this message translates to:
  /// **'حالة غير معروفة'**
  String get statusLabelUnknown;

  /// No description provided for @myOrdersEmptyTitle.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد طلبات على هذا الجهاز'**
  String get myOrdersEmptyTitle;

  /// No description provided for @myOrdersEmptyBody.
  ///
  /// In ar, this message translates to:
  /// **'الطلبات التي تنشئها من هذا التطبيق تظهر هنا مع حالة الدفع.'**
  String get myOrdersEmptyBody;

  /// No description provided for @orderStatusUnavailable.
  ///
  /// In ar, this message translates to:
  /// **'تعذّر تحديث الحالة'**
  String get orderStatusUnavailable;

  /// No description provided for @orderItemsCount.
  ///
  /// In ar, this message translates to:
  /// **'{count, plural, =1{منتج واحد} =2{منتجان} few{{count} منتجات} many{{count} منتجًا} other{{count} منتج}}'**
  String orderItemsCount(int count);
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ar', 'en'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return AppLocalizationsAr();
    case 'en':
      return AppLocalizationsEn();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
