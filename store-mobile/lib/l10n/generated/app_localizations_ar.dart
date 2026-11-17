// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Arabic (`ar`).
class AppLocalizationsAr extends AppLocalizations {
  AppLocalizationsAr([String locale = 'ar']) : super(locale);

  @override
  String get appTitle => 'متجر';

  @override
  String get heroEyebrow => 'سماعات لاسلكية';

  @override
  String get heroHeadline => 'صوت نقي\nومكالمات واضحة\nطوال يوم العمل.';

  @override
  String get heroBody =>
      'سماعات ومكبرات صوت للمكتب والبيت، بأسعار بالجنيه السوداني وتوصيل إلى الباب.';

  @override
  String get heroCta => 'تصفّح السماعات';

  @override
  String get featuresEyebrow => 'المزايا';

  @override
  String get featureBatteryTitle => 'بطارية تكفي\nيوم العمل';

  @override
  String get featureBatteryBody =>
      'شحنة واحدة تكفي يوم عمل كامل، والشحن السريع يعطيك ساعات في دقائق.';

  @override
  String get featureMicTitle => 'مكالمات\nواضحة';

  @override
  String get featureMicBody =>
      'ميكروفونات تخفف ضجيج المكان فيسمعك الطرف الآخر بوضوح.';

  @override
  String get featureComfortTitle => 'مريحة\nلساعات';

  @override
  String get featureComfortBody =>
      'وسائد ناعمة ووزن خفيف لجلسات العمل والدراسة الطويلة.';

  @override
  String get productsEyebrow => 'المنتجات';

  @override
  String get categoryHeadphones => 'سماعات رأس';

  @override
  String get categoryEarbuds => 'سماعات أذن';

  @override
  String get categorySpeakers => 'مكبرات صوت';

  @override
  String get categoryAccessories => 'إكسسوارات';

  @override
  String get allCategories => 'الكل';

  @override
  String get allProducts => 'كل المنتجات';

  @override
  String get sort => 'الترتيب';

  @override
  String get sortNewest => 'الأحدث';

  @override
  String get sortPriceLowToHigh => 'السعر من الأقل';

  @override
  String get sortPriceHighToLow => 'السعر من الأعلى';

  @override
  String get searchTooltip => 'بحث';

  @override
  String get searchHint => 'ابحث باسم المنتج';

  @override
  String get closeSearch => 'أغلق البحث';

  @override
  String get noResultsTitle => 'لا توجد منتجات بهذا الاسم';

  @override
  String get noResultsBody => 'جرّب كلمة أخرى أو اختر «الكل».';

  @override
  String get productDetailsHint => 'اعرض التفاصيل';

  @override
  String get testCardHint =>
      'للتجربة: البطاقة 4242 4242 4242 4242، أي تاريخ انتهاء قادم، أي CVC، ورمز التحقق 1234.';

  @override
  String get addToCart => 'أضف للسلة';

  @override
  String addedToCart(String name) {
    return 'أضفنا $name إلى السلة';
  }

  @override
  String get maxQuantityReached => 'الحد الأعلى 10 قطع من المنتج الواحد';

  @override
  String get cartTooltip => 'السلة';

  @override
  String cartItemCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'في السلة $count قطعة',
      many: 'في السلة $count قطعة',
      few: 'في السلة $count قطع',
      two: 'في السلة قطعتان',
      one: 'في السلة قطعة واحدة',
      zero: 'السلة فارغة',
    );
    return '$_temp0';
  }

  @override
  String get myOrdersTitle => 'طلباتي';

  @override
  String get languageToggle => 'EN';

  @override
  String get changeLanguageTooltip => 'غيّر اللغة';

  @override
  String get productsEmptyTitle => 'لا توجد منتجات حاليًا';

  @override
  String get productsEmptyBody =>
      'لم يضف المتجر منتجات بعد. اسحب الشاشة للأسفل لتحديثها.';

  @override
  String get retry => 'حاول مرة أخرى';

  @override
  String get errorNetwork =>
      'تعذّر الاتصال بالمتجر. تأكد من الإنترنت وأن خادم متجر يعمل، ثم حاول مرة أخرى.';

  @override
  String get errorGatewayUnavailable =>
      'بوابة الدفع لا تستجيب الآن. انتظر قليلًا وحاول مرة أخرى.';

  @override
  String get errorThrottled =>
      'طلبات كثيرة في وقت قصير. انتظر دقيقة ثم حاول مرة أخرى.';

  @override
  String errorServer(String code) {
    return 'حدث خلل في خادم المتجر (رمز $code). حاول بعد قليل.';
  }

  @override
  String get errorNotFound => 'لم نجد هذا الطلب.';

  @override
  String get errorParsing =>
      'وصلنا رد غير مفهوم من المتجر. حدّث التطبيق أو أبلغ فريق المتجر.';

  @override
  String get errorUnknown => 'حدث شيء لم نتوقعه. حاول مرة أخرى.';

  @override
  String get errorValidation => 'راجع البيانات المكتوبة ثم حاول مرة أخرى.';

  @override
  String amountSdg(String amount) {
    return '$amount ج.س';
  }

  @override
  String get cartTitle => 'السلة';

  @override
  String get cartEmptyTitle => 'سلتك فارغة';

  @override
  String get cartEmptyBody => 'أضف منتجًا من صفحة المنتجات ثم ارجع هنا للدفع.';

  @override
  String get browseProducts => 'تصفّح المنتجات';

  @override
  String get increaseQuantity => 'زيادة الكمية';

  @override
  String get decreaseQuantity => 'إنقاص الكمية';

  @override
  String get removeItem => 'احذف من السلة';

  @override
  String quantityValue(int quantity) {
    return 'الكمية $quantity';
  }

  @override
  String get cartTotal => 'الإجمالي';

  @override
  String get customerDetailsTitle => 'بياناتك';

  @override
  String get customerNameLabel => 'الاسم';

  @override
  String get customerPhoneLabel => 'رقم الهاتف';

  @override
  String get customerPhoneHint => '+249 912 345 678';

  @override
  String get nameRequired => 'اكتب اسمك';

  @override
  String get nameTooLong => 'الاسم أطول من 80 حرفًا';

  @override
  String get phoneRequired => 'اكتب رقم هاتفك';

  @override
  String get phoneInvalid => 'اكتب من 8 إلى 16 رقمًا، ويمكن أن يبدأ الرقم بـ +';

  @override
  String payAmount(String amount) {
    return 'ادفع $amount';
  }

  @override
  String get paymentSecureNote =>
      'تكتب بيانات البطاقة في صفحة بوابة MatmoomPay، ولا يراها التطبيق ولا المتجر.';

  @override
  String get paymentTitle => 'الدفع';

  @override
  String get closePayment => 'أغلق صفحة الدفع';

  @override
  String get paymentLoadFailed =>
      'تعذّر فتح صفحة الدفع. تأكد من الاتصال وأن بوابة MatmoomPay تعمل.';

  @override
  String get reload => 'أعد التحميل';

  @override
  String get orderResultTitle => 'حالة الطلب';

  @override
  String get statusPaidTitle => 'تم الدفع';

  @override
  String statusPaidBody(String amount) {
    return 'وصلنا مبلغ $amount. سنتصل بك على رقمك لترتيب التوصيل.';
  }

  @override
  String get orderReference => 'رقم الطلب';

  @override
  String get invoiceNumber => 'رقم الفاتورة';

  @override
  String get paidAt => 'وقت الدفع';

  @override
  String get orderTotal => 'المبلغ';

  @override
  String get statusConfirmingTitle => 'نتأكد من الدفع';

  @override
  String get statusConfirmingBody =>
      'ننتظر تأكيد بوابة الدفع، ويأخذ هذا عادةً بضع ثوانٍ.';

  @override
  String get statusPendingTitle => 'لم يصلنا تأكيد الدفع بعد';

  @override
  String get statusPendingBody =>
      'إذا خُصم المبلغ من بطاقتك فسيظهر الطلب مدفوعًا خلال دقائق. تحقق مرة أخرى بعد قليل.';

  @override
  String get checkAgain => 'تحقق مرة أخرى';

  @override
  String get statusFailedTitle => 'لم ينجح الدفع';

  @override
  String get statusFailedBody =>
      'رفضت البوابة العملية ولم يُخصم أي مبلغ. جرّب مرة أخرى أو استخدم بطاقة أخرى.';

  @override
  String get statusCancelledTitle => 'ألغيت الدفع';

  @override
  String get statusCancelledBody =>
      'لم يُخصم أي مبلغ، ومنتجاتك ما زالت في السلة.';

  @override
  String get statusExpiredTitle => 'انتهت مهلة الدفع';

  @override
  String get statusExpiredBody =>
      'انتهت صلاحية صفحة الدفع قبل إتمامها. ارجع إلى السلة وابدأ من جديد.';

  @override
  String get statusAbandonedTitle => 'لم يكتمل الدفع';

  @override
  String get statusAbandonedBody =>
      'أُغلقت صفحة الدفع قبل إتمامها. ارجع إلى السلة وابدأ من جديد.';

  @override
  String get tryAgain => 'جرّب مرة أخرى';

  @override
  String get continueShopping => 'تابع التسوق';

  @override
  String get statusLabelPending => 'بانتظار الدفع';

  @override
  String get statusLabelPaid => 'مدفوع';

  @override
  String get statusLabelFailed => 'فشل الدفع';

  @override
  String get statusLabelCancelled => 'ملغي';

  @override
  String get statusLabelExpired => 'منتهي';

  @override
  String get statusLabelAbandoned => 'غير مكتمل';

  @override
  String get statusLabelUnknown => 'حالة غير معروفة';

  @override
  String get myOrdersEmptyTitle => 'لا توجد طلبات على هذا الجهاز';

  @override
  String get myOrdersEmptyBody =>
      'الطلبات التي تنشئها من هذا التطبيق تظهر هنا مع حالة الدفع.';

  @override
  String get orderStatusUnavailable => 'تعذّر تحديث الحالة';

  @override
  String orderItemsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count منتج',
      many: '$count منتجًا',
      few: '$count منتجات',
      two: 'منتجان',
      one: 'منتج واحد',
    );
    return '$_temp0';
  }
}
