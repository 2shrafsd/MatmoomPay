<div align="center">

# MatmoomPay

**بوابة دفع إلكتروني متكاملة، ومتجر تجريبي على الويب، وتطبيق جوال يدفع عبرها**

**A complete online payment gateway, a demo web store, and a mobile app that pays through it**

[العربية](#العربية) · [English](#english)

<img src="docs/screenshots/gateway/01-landing.png" alt="MatmoomPay" width="900">

</div>

---

<div dir="rtl">

<a id="العربية"></a>

# العربية

## الفهرس

1. [نظرة عامة](#نظرة-عامة)
2. [مكونات المشروع وكيف يكمل بعضها بعضًا](#مكونات-المشروع-وكيف-يكمل-بعضها-بعضا)
3. [المميزات والخدمات](#المميزات-والخدمات)
4. [طرق الحماية والتأمين](#طرق-الحماية-والتأمين)
5. [صور الواجهات](#صور-الواجهات)
6. [التشغيل خطوة بخطوة](#التشغيل-خطوة-بخطوة)
7. [الحسابات التجريبية وبطاقات الاختبار](#الحسابات-التجريبية-وبطاقات-الاختبار)
8. [ربط متجر بالبوابة](#ربط-متجر-بالبوابة)
9. [ربط البوابة بالبنك](#ربط-البوابة-بالبنك)
10. [النشر في بيئة الإنتاج](#النشر-في-بيئة-الإنتاج)
11. [الاختبارات وضمان الجودة](#الاختبارات-وضمان-الجودة)
12. [هيكل المستودع](#هيكل-المستودع)
13. [سجل الإصدارات](#سجل-الإصدارات)

---

## نظرة عامة

**MatmoomPay** بوابة دفع إلكتروني كاملة تتيح للتجار والمتاجر الإلكترونية والتطبيقات قبول المدفوعات بالبطاقات البنكية (فيزا وماستركارد، مدينة وائتمانية)، وإدارة أموالهم وعملائهم واشتراكاتهم وتسوياتهم من لوحة واحدة، مع واجهة برمجة (API) واضحة يربط بها المطور متجره في دقائق.

**المشروع مقتبس من طريقة عمل شركة ثواني (Thawani) العُمانية** للمدفوعات الإلكترونية: يتبع نموذج عملها نفسه، من جلسات الدفع وصفحة الدفع المستضافة والعملاء والبطاقات المحفوظة ونوايا الدفع والاستردادات، إلى صيغة الردود ورموز الحالات وتوقيع الإشعارات (Webhooks). لذلك يجد المطور الذي سبق له الربط مع ثواني الطريقة مألوفة. والمشروع يحمل هويته الخاصة باسمه وتصميمه ونصوصه، ولا يرتبط بشركة ثواني ولا يستخدم اسمها أو شعارها.

- **العملة الافتراضية:** الجنيه السوداني (SDG) بالجنيه الكامل دون كسور، والعملة وعدد خاناتها قابلان للتغيير من الإعدادات.
- **اللغة:** العربية أولًا من اليمين لليسار، مع الإنجليزية كاملةً.
- **وضعان منفصلان تمامًا:** وضع **الاختبار** ببطاقات وهمية وبنك تجريبي، ووضع **حقيقي** يعمل بعد ربط البنك. لكل وضع مفاتيحه وبياناته.

---

## مكونات المشروع وكيف يكمل بعضها بعضًا

المستودع مشروع واحد من ثلاثة أجزاء، كل جزء يعتمد على الذي قبله:

| الجزء | المجلد | التقنية | الدور |
|---|---|---|---|
| **1. البوابة** | [`gateway/`](gateway) | Laravel 13، Livewire 4، Flux UI، Tailwind CSS 4، MySQL | تستقبل المدفوعات وتعالجها، وفيها صفحة الدفع، ولوحة التاجر، ولوحة المالك، وواجهة البرمجة |
| **2. المتجر التجريبي (ويب)** | [`store-web/`](store-web) | Laravel 13، SQLite | متجر سماعات حقيقي الشكل يدفع زبائنه عبر البوابة، ويقدم واجهة برمجة لتطبيق الجوال |
| **3. تطبيق الجوال** | [`store-mobile/`](store-mobile) | Flutter، Riverpod، go_router، Dio | تطبيق المتجر نفسه على iOS وAndroid وmacOS، يفتح صفحة الدفع داخل التطبيق |

### مسار الدفع الكامل

```mermaid
sequenceDiagram
    autonumber
    actor C as الزبون
    participant M as تطبيق الجوال
    participant S as المتجر (store-web)
    participant G as البوابة (gateway)
    participant B as البنك

    C->>S: يضيف المنتجات إلى السلة ويضغط «ادفع»
    Note over M,S: أو من التطبيق: POST /api/orders
    S->>G: POST /api/v1/checkout/session (بالمفتاح السري)
    G-->>S: session_id
    S-->>C: تحويل إلى صفحة الدفع /pay/{session_id}?key=المفتاح_العام
    C->>G: يكتب بيانات البطاقة
    G->>B: طلب الخصم
    B-->>G: يطلب رمز التحقق OTP أو 3-D Secure
    C->>G: يكتب رمز التحقق
    G-->>C: تحويل إلى success_url الخاص بالمتجر
    G-)S: إشعار Webhook موقّع (checkout.completed)
    S->>G: يتأكد من حالة الجلسة قبل اعتماد الطلب
    S-->>C: «تم الدفع» وتأكيد الطلب
```

> **قاعدة أساسية في التصميم:** المتجر لا يعتمد على تحويل الزبون إلى رابط النجاح وحده، لأن أي شخص يستطيع فتح ذلك الرابط بيده. الطلب يُعتمد فقط بعد أن يسأل المتجر البوابة عن حالة الجلسة، أو بعد وصول إشعار Webhook موقّع.

---

## المميزات والخدمات

### 1. واجهة برمجة للمطورين (REST API) على `/api/v1`

| الخدمة | المسارات | الوصف |
|---|---|---|
| جلسات الدفع | `POST/GET /checkout/session`، `GET /checkout/session/{id}`، `GET /checkout/reference/{ref}`، `GET /checkout/invoice/{invoice}`، `POST /checkout/{id}/cancel` | إنشاء جلسة دفع بالمنتجات والمبالغ وروابط العودة، ثم تحويل الزبون إلى صفحة الدفع |
| العملاء | `POST/GET /customers`، `GET/DELETE /customers/{id}` | تسجيل العملاء لحفظ بطاقاتهم واستخدامها لاحقًا |
| البطاقات المحفوظة | `GET /payment_methods`، `DELETE /payment_methods/{id}` | بطاقات العميل المحفوظة (رمز مميّز فقط، لا رقم البطاقة) |
| نوايا الدفع | `POST/GET /payment_intents`، `confirm`، `cancel` | خصم من بطاقة محفوظة دون حضور العميل، مثل الطلبات المتكررة |
| المدفوعات | `GET /payments`، `GET /payments/{id}` | البحث في العمليات وقراءة تفاصيلها |
| الاستردادات | `POST/GET /refunds`، `GET /refunds/{id}` | استرداد كامل أو جزئي، مع منع تجاوز المبلغ المدفوع |
| الخطط والاشتراكات | `/plans`، `/subscriptions`، `cancel`، `invoices` | خصم دوري أسبوعي أو شهري أو سنوي |

- الردود كلها بصيغة موحدة: `{ success, code, description, data }` مع رموز حالة واضحة.
- المصادقة بالترويسة `matmoom-api-key`، ومفتاحان لكل وضع: **سري** للخادم و**عام** لصفحة الدفع.
- دعم الترويسة `Idempotency-Key` لإعادة إرسال الطلب بأمان دون إنشاء عملية مكررة.
- ترقيم الصفحات والتصفية في كل القوائم، وحقل `metadata` حر لبيانات المتجر.

### 2. صفحة الدفع المستضافة

- صفحة دفع جاهزة على `/pay/{session_id}` يحوّل إليها المتجر زبونه، فلا يحتاج المتجر إلى بناء نموذج بطاقة.
- مصممة للجوال أولًا، بالعربية والإنجليزية، وتعرض المنتجات والمبلغ وصلاحية الجلسة.
- تحقق فوري من رقم البطاقة (خوارزمية Luhn) والتاريخ ورمز الأمان، وتنسيق تلقائي للأرقام.
- التحقق برمز OTP أو 3-D Secure حسب البطاقة، مع عدد محدود من المحاولات.
- الدفع بالبطاقات المحفوظة بضغطة واحدة، وخيار حفظ البطاقة.
- وضع **الدفع عبر صفحة البنك** (Hosted Payment Page): يكتب الزبون بطاقته في صفحة البنك نفسه فلا يمر رقمها بخوادم البوابة إطلاقًا.

### 3. روابط الدفع ورموز QR

- إنشاء رابط دفع بمبلغ ثابت أو مبلغ يحدده الدافع، دون أي برمجة.
- رمز QR لكل رابط، ومشاركة مباشرة على واتساب.
- خيار جمع رقم هاتف الدافع وبريده، وإرسال إيصال له بالبريد.

### 4. لوحة التاجر

| القسم | ما يقدمه |
|---|---|
| نظرة عامة | الرصيد المتاح، والمحصّل اليوم وفي 30 يومًا، ونسبة النجاح، ورسم يومي، وآخر العمليات |
| العمليات | بحث وتصفية بالحالة والتاريخ، وتفاصيل كل دفعة، واسترداد كامل أو جزئي، وتصدير CSV |
| جلسات الدفع | كل جلسة وحالتها (مفتوحة، مدفوعة، ملغاة، منتهية) |
| الاستردادات والعملاء | سجل الاستردادات، والعملاء ومدفوعاتهم وبطاقاتهم المحفوظة |
| روابط الدفع | إنشاء الروابط وإدارتها ورموز QR |
| الخطط والاشتراكات | إنشاء خطط الخصم الدوري ومتابعة المشتركين وإلغاء الاشتراكات |
| الرصيد والتسويات | الحركات المالية (دفعات ورسوم واستردادات)، والتسويات المحوّلة إلى حساب التاجر البنكي، وتصدير كشف كل تسوية |
| المطورون | مفاتيح API للوضعين، وعناوين Webhook مع سجل كل إرسال وإعادة الإرسال يدويًا |
| ملف النشاط | بيانات المنشأة والحساب البنكي (IBAN) ومستندات التحقق (السجل التجاري، هوية المالك، خطاب البنك) |
| الفريق | دعوة الأعضاء بالبريد بخمسة أدوار: مالك، مدير، مطوّر، مالية، مُطّلع |

- التبديل بين وضع الاختبار والوضع الحقيقي بضغطة، مع شريط تنبيه واضح في وضع الاختبار.
- دعم أكثر من نشاط تجاري للمستخدم الواحد، والتبديل بينها.

### 5. لوحة المالك (إدارة المنصة)

- نظرة عامة على حجم المدفوعات الحقيقية والرسوم المحصلة ومستحقات التجار.
- **مراجعة التجار:** قراءة ملف النشاط والمستندات، ثم اعتماد التاجر أو رفضه أو تعليقه أو حظره مع ذكر السبب. لا يُعتمد تاجر لأول مرة قبل قبول مستنداته الثلاثة.
- إدارة المستخدمين: حظر مستخدم أو رفع الحظر، وأرشفة التجار.
- كل المدفوعات على المنصة، وإنشاء التسويات للتجار ومتابعتها.
- **سجل تدقيق** غير قابل للتعديل لكل إجراء حساس.

### 6. الإشعارات

- **Webhooks** لـ 19 حدثًا، منها: `checkout.completed`، `payment.succeeded`، `payment.failed`، `refund.succeeded`، `subscription.created`، `invoice.paid`، `settlement.paid`.
- إعادة إرسال تلقائية عند الفشل، وتنبيه التاجر إذا تعطل عنوانه.
- **بريد إلكتروني** للتاجر عند كل دفعة واسترداد وتسوية وتغيير حالة الحساب، وإيصال للدافع.

### 7. الاشتراكات والخصم الدوري

- أول دفعة تحفظ البطاقة وتبدأ الاشتراك، ثم مهمة مجدولة كل 15 دقيقة تخصم التجديدات.
- عند فشل التجديد تُعاد المحاولة بعد يوم ثم 3 أيام ثم 5 أيام، ثم يُلغى الاشتراك.
- إذا طلب البنك تحققًا، تحمل الفاتورة رابطًا يرسله التاجر لعميله ليؤكد الدفع.

### 8. الرسوم والتسويات

- رسوم قابلة للضبط: نسبة مئوية (افتراضيًا 2.5%) ومبلغ ثابت وحد أدنى.
- سجل مالي (Ledger) لكل دفعة ورسومها وكل استرداد، تُبنى عليه التسويات.
- فترة احتجاز قبل التسوية (افتراضيًا يومان) قابلة للضبط.
- مهمة مطابقة كل دقيقة تحسم العمليات معلّقة النتيجة وتُنهي الجلسات المنتهية.

---

## طرق الحماية والتأمين

| المجال | طريقة الحماية |
|---|---|
| **بيانات البطاقات** | لا يُخزَّن رقم البطاقة ولا رمز الأمان CVV إطلاقًا. يُحفظ رمز مميّز (Token) مشفّر من المعالج، وأول 6 أرقام وآخر 4 فقط |
| **نطاق PCI DSS** | وضع الدفع عبر صفحة البنك يجعل الزبون يكتب بطاقته في صفحة البنك، فينتقل نطاق الامتثال في صفحة الدفع إلى النطاق الأخف SAQ A |
| **المفاتيح والأسرار** | المفتاح السري يُحفظ مجزّأً (Hash) ويظهر للتاجر مرة واحدة عند إنشائه. رقم IBAN وسر توقيع Webhook مشفّران في قاعدة البيانات |
| **فصل البيانات** | كل استعلام مقيّد بالتاجر والوضع (اختبار أو حقيقي). طلب سجل تاجر آخر يعيد 404 دون كشف وجوده |
| **الصلاحيات** | تفويض على الخادم في كل إجراء حسب دور العضو، وتأكيد كلمة المرور قبل صفحات المفاتيح والحساب البنكي والفريق. يُعاد فحص التأكيد مع كل تحديث Livewire ولا يمكن تجاوزه بتغيير رؤوس الطلب، وصفحات السجلات تعيد فحص الصلاحية مع كل تحديث |
| **الجلسات واستعادة الحساب** | تغيير كلمة المرور أو إعادة تعيينها ينهي كل الجلسات الأخرى. تغيير البريد (قناة الاستعادة) يتطلب كلمة المرور الحالية ويُشعر البريد القديم. روابط البريد في الإنتاج تُبنى من `APP_URL`، والمضيف الموثوق مطابق حرفيًا لاسم التطبيق |
| **الفريق** | الدعوات تسقط عند إزالة مرسلها أو خفض دوره، ويُفحص المرسل من جديد عند القبول |
| **حساب التسوية** | توثيق المشرف مربوط ببصمة الحساب الذي راجعه، فأي تبديل لرقم IBAN بعد فتح الصفحة يُرفض |
| **الدفع المزدوج والسباقات** | أقفال على مستوى الصف في قاعدة البيانات تمنع الخصم المزدوج والاسترداد الزائد، ومفتاح `Idempotency-Key` لإعادة المحاولة الآمنة. إذا دُفعت جلسة مرتين في اللحظة نفسها تُسترد الدفعة الثانية تلقائيًا |
| **إشعارات Webhook** | توقيع HMAC-SHA256 على `{body}-{timestamp}` في الترويستين `matmoom-signature` و`matmoom-timestamp`، ورفض الإشعارات الأقدم من 5 دقائق |
| **حماية SSRF** | منع إرسال Webhook إلى العناوين الداخلية والخاصة، مع تثبيت عنوان IP بعد التحقق منه |
| **رد البنك** | التحقق من توقيع البنك، ومطابقة المرجع والمبلغ والعملة مع الطلب الأصلي، واكتمال كل محاولة مرة واحدة فقط، ورفض الردود القديمة (أكثر من 15 دقيقة) |
| **الحد من الإساءة** | حدود معدل (Rate Limiting) على تسجيل الدخول والدفع ورمز التحقق وواجهة البرمجة (لكل مفتاح ولكل عنوان IP) والتصدير والدعوات، وعلى التسجيل ونسيت كلمة المرور وتأكيد كلمة المرور وإعادة إرسال بريد التحقق وتجربة الـ Webhook، ولكل رابط دفع. يُحتسب نطاق IPv6 ‏/64 كاملًا عنوانًا واحدًا |
| **البريد** | إيصالات وضع الاختبار تصل إلى فريق التاجر فقط، فلا تُستعمل البوابة لإرسال بريد إلى الغرباء. كل نص يكتبه التاجر يُهرَّب قبل إدراجه في الرسائل |
| **الأموال في الخلفية** | ترتيب أقفال موحّد عند إكمال 3-D Secure، وإلغاء نية الدفع المعلّقة عند إلغاء الاشتراك، واسترداد آلي لأي تجديد يصل بعد الإلغاء، وسلسلة إعادة محاولة واحدة لكل Webhook |
| **المتصفح** | سياسة أمان المحتوى CSP مبنية على nonce، ومنع تضمين صفحة الدفع في إطار (Clickjacking)، وترويسات HSTS وX-Content-Type-Options وReferrer-Policy |
| **الحسابات** | تحقق ثنائي (2FA) إلزامي للمشرفين، ودعم مفاتيح المرور (Passkeys)، وحظر الحسابات، وتسجيل أحداث الأمان (تفعيل 2FA، تغيير كلمة المرور، إضافة مفاتيح المرور) |
| **الملفات المرفوعة** | فحص نوع الملف من محتواه الفعلي لا من امتداده، وتخزين خاص بأسماء عشوائية، وتنزيل إجباري كمرفق بعد التحقق من الصلاحية. لا يُعطى رابط رفع مؤقت إلا لمن يحق له رفع المستندات، وبأنواع KYC حتى 5MB، مع حذف آلي كل ساعة للملفات المؤقتة المتروكة |
| **تصدير CSV** | حماية من حقن الصيغ في جداول البيانات (Formula Injection) |
| **التدقيق** | سجل غير قابل للتعديل لكل إجراء حساس، مع رقم طلب فريد (Request ID) في كل رد |
| **الإنتاج** | أمر `php artisan gateway:check-production` يفحص 14 إعدادًا حساسًا ويفشل إن وجد خللًا |

---

## صور الواجهات

> كل البيانات في الصور خيالية، من البيانات التجريبية للمشروع.

### البوابة: الصفحة التعريفية والتوثيق

| الصفحة التعريفية (جوال) | توثيق واجهة البرمجة | تسجيل الدخول |
|---|---|---|
| <img src="docs/screenshots/gateway/01-landing-mobile.png" width="220"> | <img src="docs/screenshots/gateway/02-developer-docs.png" width="380"> | <img src="docs/screenshots/gateway/03-login.png" width="380"> |

### لوحة التاجر

<img src="docs/screenshots/merchant/01-dashboard.png" alt="لوحة التاجر" width="900">

| العمليات | تفاصيل دفعة | جلسات الدفع |
|---|---|---|
| <img src="docs/screenshots/merchant/02-payments.png" width="290"> | <img src="docs/screenshots/merchant/03-payment-details.png" width="290"> | <img src="docs/screenshots/merchant/04-checkouts.png" width="290"> |
| **روابط الدفع** | **تفاصيل رابط ورمز QR** | **العملاء** |
| <img src="docs/screenshots/merchant/07-payment-links.png" width="290"> | <img src="docs/screenshots/merchant/08-payment-link-details.png" width="290"> | <img src="docs/screenshots/merchant/06-customers.png" width="290"> |
| **الخطط** | **الاشتراكات** | **الاستردادات** |
| <img src="docs/screenshots/merchant/09-plans.png" width="290"> | <img src="docs/screenshots/merchant/10-subscriptions.png" width="290"> | <img src="docs/screenshots/merchant/05-refunds.png" width="290"> |
| **الرصيد** | **التسويات** | **الفريق** |
| <img src="docs/screenshots/merchant/11-balance.png" width="290"> | <img src="docs/screenshots/merchant/12-settlements.png" width="290"> | <img src="docs/screenshots/merchant/13-team.png" width="290"> |
| **مفاتيح API** | **Webhooks** | **ملف النشاط والحساب البنكي** |
| <img src="docs/screenshots/merchant/14-api-keys.png" width="290"> | <img src="docs/screenshots/merchant/15-webhooks.png" width="290"> | <img src="docs/screenshots/merchant/16-business-profile.png" width="290"> |

<img src="docs/screenshots/merchant/01-dashboard-mobile.png" alt="لوحة التاجر على الجوال" width="240">

### لوحة المالك (إدارة المنصة)

<img src="docs/screenshots/admin/01-dashboard.png" alt="لوحة المالك" width="900">

| التجار | مراجعة تاجر واعتماده | المستخدمون |
|---|---|---|
| <img src="docs/screenshots/admin/02-merchants.png" width="290"> | <img src="docs/screenshots/admin/03-merchant-review.png" width="290"> | <img src="docs/screenshots/admin/04-users.png" width="290"> |
| **المدفوعات** | **التسويات** | **سجل التدقيق** |
| <img src="docs/screenshots/admin/05-payments.png" width="290"> | <img src="docs/screenshots/admin/06-settlements.png" width="290"> | <img src="docs/screenshots/admin/07-audit-log.png" width="290"> |

### المتجر التجريبي

| واجهة المتجر | السلة وبيانات الزبون | طلباتي |
|---|---|---|
| <img src="docs/screenshots/store/01-shop.png" width="290"> | <img src="docs/screenshots/store/02-cart.png" width="290"> | <img src="docs/screenshots/store/03-orders.png" width="290"> |

### عملية الدفع من البداية إلى النهاية

| 1. صفحة الدفع | 2. بيانات البطاقة | 3. رمز التحقق | 4. الطلب مدفوع |
|---|---|---|---|
| <img src="docs/screenshots/payment/01-hosted-checkout-mobile.png" width="200"> | <img src="docs/screenshots/payment/02-card-details.png" width="300"> | <img src="docs/screenshots/payment/03-otp-verification-mobile.png" width="200"> | <img src="docs/screenshots/payment/04-order-paid.png" width="300"> |

| رابط دفع على الجوال |
|---|
| <img src="docs/screenshots/payment/05-payment-link.png" width="220"> |

### تطبيق الجوال

| المنتجات | السلة | الطلب مدفوع | الوضع الداكن |
|---|---|---|---|
| <img src="docs/screenshots/mobile/products_light.png" width="200"> | <img src="docs/screenshots/mobile/cart_light.png" width="200"> | <img src="docs/screenshots/mobile/order_paid_light.png" width="200"> | <img src="docs/screenshots/mobile/products_grid_dark.png" width="200"> |

---

## التشغيل خطوة بخطوة

### المتطلبات

| الأداة | الإصدار |
|---|---|
| PHP | 8.4 أو أحدث (مع امتدادات Laravel المعتادة وsqlite وpdo_mysql) |
| Composer | 2 |
| Node.js وnpm | 22 |
| MySQL | 8 (أو SQLite للتجربة السريعة) |
| Flutter | 3.47 أو أحدث (Dart 3.13+) لتطبيق الجوال فقط |

### 1. تشغيل البوابة (`gateway/`)

```bash
cd gateway
composer setup          # يثبت الحزم وينشئ .env والمفتاح ويهيئ قاعدة البيانات ويبني الواجهة
```

عدّل اتصال قاعدة البيانات في `gateway/.env` (أو اترك `DB_CONNECTION=sqlite`)، ثم:

```bash
php artisan migrate --seed      # يطبع في الطرفية بيانات الدخول والمفتاح السري التجريبي
php artisan serve --port=8000   # البوابة على http://localhost:8000
php artisan queue:work          # في نافذة ثانية: ضروري لإرسال Webhooks والبريد والتجديدات
php artisan schedule:work       # في نافذة ثالثة: التجديدات والمطابقة والتنظيف الدوري
```

أو شغّلها كلها معًا: `composer run dev`.

### 2. تشغيل المتجر التجريبي (`store-web/`)

```bash
cd store-web
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed      # ينشئ منتجات المتجر
```

ادخل لوحة التاجر في البوابة (`merchant@matmoompay.test`)، ثم من **المطورون ← مفاتيح API** أنشئ مفتاحًا سريًا ومفتاحًا عامًا في وضع الاختبار، وضعهما في `store-web/.env`:

```dotenv
MATMOOM_BASE_URL=http://localhost:8000/api/v1
MATMOOM_PAY_URL=http://localhost:8000/pay
MATMOOM_SECRET_KEY=sk_test_...
MATMOOM_PUBLISHABLE_KEY=pk_test_...
MATMOOM_WEBHOOK_SECRET=        # من صفحة Webhooks بعد إضافة العنوان (اختياري)
```

```bash
php artisan serve --port=8001   # المتجر على http://localhost:8001
```

لاستقبال إشعارات Webhook محليًا: أضف في لوحة التاجر عنوان `http://localhost:8001/webhooks/matmoom`، وانسخ سر التوقيع إلى `MATMOOM_WEBHOOK_SECRET`، وفعّل في `gateway/.env` الخيار `GATEWAY_WEBHOOKS_ALLOW_PRIVATE_HOSTS=true` (للتطوير المحلي فقط، ويبقى `false` في الإنتاج).

### 3. تشغيل تطبيق الجوال (`store-mobile/`)

التطبيق يتصل بالمتجر (لا بالبوابة مباشرة)، فشغّل البوابة والمتجر أولًا.

```bash
cd store-mobile
flutter pub get
flutter run                                              # محاكي iOS أو macOS (العنوان الافتراضي http://localhost:8001)
flutter run -d emulator-5554                             # محاكي Android (العنوان الافتراضي http://10.0.2.2:8001)
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8001   # جهاز حقيقي على الشبكة نفسها
```

يفتح التطبيق صفحة الدفع داخل WebView، ويلتقط التحويل إلى رابط النجاح أو الإلغاء، ثم يسأل المتجر عن حالة الطلب (`GET /api/orders/{reference}` مع الترويسة `X-Order-Token`) ويعيد السؤال كل ثانيتين حتى 20 ثانية ما دامت الحالة معلّقة.

---

## الحسابات التجريبية وبطاقات الاختبار

### الحسابات (بعد `php artisan migrate --seed` في البوابة)

| الحساب | البريد | كلمة المرور | ملاحظات |
|---|---|---|---|
| مالك المنصة (المشرف) | `admin@matmoompay.test` | `password` | لوحة `/admin`. أول دخول يطلب تفعيل التحقق الثنائي من صفحة الأمان |
| التاجر | `merchant@matmoompay.test` | `password` | لوحة `/dashboard` لنشاط تجاري معتمد اسمه Nile Aroma، فيه مدفوعات وروابط وخطة واشتراك تجريبية |

يطبع أمر البذر أيضًا **مفتاحًا سريًا تجريبيًا** (`sk_test_...`) يمكن استخدامه مباشرة مع واجهة البرمجة. وفي التطوير المحلي يمكن إيقاف شرط التحقق الثنائي للمشرف بـ `SECURITY_ADMIN_REQUIRES_2FA=false` (لا يُستخدم في الإنتاج).

### بطاقات الاختبار

تعمل في وضع الاختبار فقط. تاريخ الانتهاء أي تاريخ قادم، ورمز الأمان CVC أي 3 أرقام، و**رمز التحقق OTP دائمًا `1234`**.

| رقم البطاقة | النوع | النتيجة |
|---|---|---|
| `4242 4242 4242 4242` | Visa مدينة | تُقبل بعد رمز التحقق |
| `5123 4500 0000 0008` | Mastercard ائتمانية | تُقبل بعد رمز التحقق |
| `4000 0000 0000 0002` | Visa مدينة | تُرفض بعد رمز التحقق |
| `4456 5300 0000 1096` | Visa ائتمانية | تحقق 3-D Secure، تُقبل |
| `4456 5300 0000 1104` | Visa ائتمانية | تحقق 3-D Secure، تُرفض |

لتجربة مسار **الدفع عبر صفحة البنك** بدل نموذج البطاقة، ضع في `gateway/.env` القيمة `GATEWAY_SANDBOX_FLOW=hosted`، فتحوّل صفحة الدفع الزبون إلى البنك التجريبي على `/sandbox-bank`.

---

## ربط متجر بالبوابة

هذه الخطوات لأي متجر (Laravel أو غيره أو تطبيق جوال عبر خادمه). والمتجر التجريبي في `store-web/app/Services/MatmoomPay.php` مثال كامل جاهز للنسخ.

### الخطوة 1: احصل على المفاتيح

من لوحة التاجر: **المطورون ← مفاتيح API**.
- **المفتاح السري** `sk_test_...` / `sk_live_...`: يبقى على خادمك فقط، ولا يوضع أبدًا في تطبيق جوال أو كود متصفح.
- **المفتاح العام** `pk_test_...` / `pk_live_...`: يُلحق برابط صفحة الدفع.

### الخطوة 2: أنشئ جلسة دفع من خادمك

```bash
curl -X POST http://localhost:8000/api/v1/checkout/session \
  -H "matmoom-api-key: sk_test_..." \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: order-1001" \
  -d '{
    "client_reference_id": "order-1001",
    "mode": "payment",
    "products": [
      { "name": "Karkade 1 kg", "quantity": 2, "unit_amount": 3200 }
    ],
    "success_url": "https://your-store.test/orders/1001/return",
    "cancel_url": "https://your-store.test/cart",
    "metadata": { "Customer name": "Amna Osman" }
  }'
```

الرد:

```json
{
  "success": true,
  "code": 2001,
  "description": "object created successfully",
  "data": { "session_id": "checkout_...", "invoice": "...", "payment_status": "unpaid", "...": "..." }
}
```

> المبالغ أعداد صحيحة بأصغر وحدة للعملة. مع الجنيه السوداني (بلا كسور) القيمة `3200` تعني 3,200 ج.س.

### الخطوة 3: حوّل الزبون إلى صفحة الدفع

```
http://localhost:8000/pay/{session_id}?key={publishable_key}
```

### الخطوة 4: تحقّق من الدفع قبل اعتماد الطلب

عند عودة الزبون إلى `success_url` اسأل البوابة عن الجلسة، ولا تعتمد على وصوله وحده:

```bash
curl http://localhost:8000/api/v1/checkout/session/{session_id} -H "matmoom-api-key: sk_test_..."
# data.payment_status == "paid"  ← اعتمد الطلب
```

### الخطوة 5: استقبل إشعارات Webhook (موصى به)

أضف عنوانك من **المطورون ← Webhooks**، واحفظ سر التوقيع. كل إشعار يحمل الترويستين `matmoom-signature` و`matmoom-timestamp`. تحقّق منهما هكذا (PHP):

```php
$body      = $request->getContent();
$timestamp = $request->header('matmoom-timestamp');
$signature = $request->header('matmoom-signature');

$valid = ctype_digit($timestamp)
    && abs(time() - (int) $timestamp) <= 300
    && hash_equals(hash_hmac('sha256', $body.'-'.$timestamp, $webhookSecret), $signature);
```

ثم عالج الحدث حسب `event` (مثل `checkout.completed`) وأعد `200` بسرعة. الإشعار قد يصل أكثر من مرة، فاجعل معالجتك تتجاهل المكرر.

### ربط تطبيق جوال

لا يحمل التطبيق المفتاح السري ولا يكلّم البوابة مباشرة. التطبيق يطلب من خادم المتجر إنشاء الطلب (`POST /api/orders`)، فيُنشئ الخادم الجلسة ويعيد `payment_url` ورمز وصول للطلب، ثم يفتح التطبيق الرابط في WebView ويراقب روابط العودة. هذا بالضبط ما يفعله `store-mobile` مع `store-web`.

---

## ربط البوابة بالبنك

البوابة مبنية بحيث يُضاف البنك المستحوذ (Acquirer) دون تعديل أي جزء آخر من النظام. وضع الاختبار يعمل دائمًا عبر المعالج التجريبي، والوضع الحقيقي يرفض أي عملية حتى يُربط بنك (`GATEWAY_LIVE_PROCESSOR=unconfigured`).

### الواجهتان البرمجيتان

- **`PaymentProcessor`** (`gateway/app/Services/Payments/Contracts`): `tokenize`، `charge`، `completeChallenge`، `refund`، `retrieve`. لنموذج البطاقة في صفحتنا والبطاقات المحفوظة والاسترداد.
- **`HostedPaymentPageProcessor`**: `startHostedPayment` (يسجّل الطلب لدى البنك ويعيد رابط صفحته)، و`completeHostedPayment` (يتحقق من توقيع رد البنك ويقرأ النتيجة). هذا المسار الموصى به لأن البطاقة تُكتب في صفحة البنك.

### خطوات الربط

1. احصل من البنك على: وثائق واجهة الدفع، وبيانات بيئة الاختبار (معرّف التاجر، المفاتيح، شهادات TLS إن وجدت)، وطريقة توقيع الطلبات والردود، وعناوين العودة المطلوب تسجيلها.
2. أنشئ صنفًا في `gateway/app/Services/Payments/Processors/` يطبق الواجهتين، مثل `BankXProcessor`:
   - في `startHostedPayment` أرسل المرجع والمبلغ والعملة ورابط العودة `pay/return/{attempt}` إلى البنك، وأعد رابط صفحته.
   - في `completeHostedPayment` تحقق من توقيع البنك وارمِ `InvalidProcessorCallback` عند أي خلل. البوابة نفسها تطابق المرجع والمبلغ والعملة وتمنع تكرار الاكتمال.
   - في `refund` و`charge` استدعِ واجهات البنك للاسترداد والخصم من البطاقات المحفوظة إن كان يدعم الرموز.
3. سجّله في `PaymentProcessorResolver`:

   ```php
   return match (config('gateway.processors.live')) {
       'bank_x' => $this->container->make(BankXProcessor::class),
       default  => $this->container->make(UnconfiguredProcessor::class),
   };
   ```

4. أضف مفاتيح البنك في `config/services.php` ومتغيرات البيئة، ثم في الإنتاج:

   ```dotenv
   GATEWAY_LIVE_PROCESSOR=bank_x
   ```

5. جرّب على بيئة اختبار البنك، ثم شغّل `php artisan gateway:check-production`.

صفحة الدفع والواجهة البرمجية والتسويات والاشتراكات والإشعارات تعمل كما هي بعد الربط. والبنك التجريبي في `SandboxHostedProcessor` و`/sandbox-bank` مثال كامل للواجهة (توجيه موقّع للخارج ورد موقّع للداخل).

---

## النشر في بيئة الإنتاج

1. انسخ `gateway/.env.production.example` إلى الخادم باسم `.env` واملأ كل القيم الفارغة.
2. قبل كل نشر:

   ```bash
   php artisan gateway:check-production   # يفشل إن كان أي إعداد حساس غير آمن
   php artisan migrate --force
   php artisan optimize
   npm ci && npm run build
   ```

3. شغّل عامل الطابور بشكل دائم (`php artisan queue:work` عبر Supervisor)، وأضف إلى cron:

   ```cron
   * * * * * cd /path/to/gateway && php artisan schedule:run >> /dev/null 2>&1
   ```

4. تأكد من: HTTPS، و`APP_DEBUG=false`، و`SESSION_ENCRYPT=true`، وملفات تعريف ارتباط آمنة، وRedis للجلسات والطابور، ومزود بريد، و`TRUSTED_PROXIES` خلف موازن الأحمال.
5. قبل استقبال أموال حقيقية: ربط البنك، واستكمال متطلبات PCI DSS مع البنك، واختبار اختراق خارجي.

---

## الاختبارات وضمان الجودة

| الجزء | الأمر | الحالة الحالية |
|---|---|---|
| البوابة | `cd gateway && php artisan test --compact` | 319 اختبارًا ناجحًا (991 تحققًا) |
| البوابة | `vendor/bin/phpstan analyse` و`vendor/bin/pint --test` | Larastan المستوى 7 وتنسيق Pint |
| المتجر | `cd store-web && php artisan test --compact` | 21 اختبارًا ناجحًا |
| التطبيق | `cd store-mobile && flutter analyze && flutter test` | تحليل صارم واختبارات وحدات وواجهات ومقارنة صور |

تغطي الاختبارات: المصادقة بالمفاتيح، والفصل بين التجار وبين الوضعين، ومسار الدفع كاملًا بكل بطاقات الاختبار، ورمز التحقق ومحاولاته، والبطاقات المحفوظة، ونوايا الدفع، والاستردادات، وتوقيع Webhook وحماية SSRF، ومفاتيح عدم التكرار، والأدوار والصلاحيات، وتأكيد كلمة المرور وإنهاء الجلسات وحدود المعدل والمضيف الموثوق، ولوحة المالك، والتصدير، والمستندات، والاشتراكات وتجديدها.

ويشغّل GitHub Actions في [`.github/workflows/ci.yml`](.github/workflows/ci.yml) اختبارات الأجزاء الثلاثة مع كل رفع.

---

## هيكل المستودع

```
.
├── gateway/                  البوابة (Laravel)
│   ├── app/Actions/          منطق العمل: الدفع، الاسترداد، التسويات، الاشتراكات...
│   ├── app/Services/Payments/ واجهات المعالجات والمعالج التجريبي ونقطة ربط البنك
│   ├── app/Http/Controllers/Api/V1/  واجهة البرمجة
│   ├── resources/views/pages/        صفحات لوحة التاجر ولوحة المالك وصفحة الدفع
│   ├── lang/                 الترجمة العربية
│   └── tests/                اختبارات Pest
├── store-web/                المتجر التجريبي (Laravel)
│   └── app/Services/MatmoomPay.php   مثال الربط مع البوابة
├── store-mobile/             تطبيق الجوال (Flutter)
│   └── lib/features/         المنتجات، السلة، الدفع، الطلبات
├── docs/screenshots/         صور الواجهات
└── .github/workflows/        التكامل المستمر
```

---

## سجل الإصدارات

يُحدَّث هذا القسم مع كل رفع جديد، ويُضاف الإصدار الأحدث في الأعلى.

### 1.1.0 — 17 نوفمبر 2026 (تقوية أمنية)

- تدقيق أمني مصدري كامل على البوابة (39 وحدة تغطية، وتحقق مستقل مزدوج من كل نتيجة)، وعولجت كل نتائجه: 8 ثغرات مؤكدة و13 قضية تحتاج تحققًا.
- أهمها: إغلاق تجاوز تأكيد كلمة المرور في Livewire برأس `Accept: application/json`، ووضع صفحة الفريق خلف تأكيد كلمة المرور، واشتراط كلمة المرور لتغيير البريد، وإنهاء الجلسات الأخرى عند تغيير كلمة المرور.
- منع العضو المُزال من القراءة عبر صفحات مفتوحة، وإسقاط دعواته المعلّقة، وربط توثيق حساب التسوية بما راجعه المشرف.
- تثبيت المضيف الموثوق، وحدود معدل جديدة على مسارات البريد وتأكيد كلمة المرور، وتقييد الرفع المؤقت وحذفه آليًا، وتصحيح مسارات الأموال في التجديد وإكمال 3-D Secure.
- اختبارات انحدار جديدة في `gateway/tests/Feature/SecuritySafeguardsTest.php`.

### 1.0.0 — 17 نوفمبر 2026

- أول إصدار عام للمستودع يجمع الأجزاء الثلاثة: البوابة، والمتجر التجريبي، وتطبيق الجوال.
- البوابة: واجهة البرمجة الكاملة، وصفحة الدفع المستضافة ومسار الدفع عبر صفحة البنك، وروابط الدفع ورموز QR، والاشتراكات، والتسويات، ولوحة التاجر، ولوحة المالك مع إدارة المستخدمين والحظر والأرشفة، وسجل التدقيق.
- المتجر التجريبي: متجر ويب يدفع عبر البوابة، مع واجهة برمجة لتطبيق الجوال واستقبال Webhooks.
- تطبيق الجوال: خمس شاشات (المنتجات، السلة، الدفع، النتيجة، طلباتي) بالعربية والإنجليزية والوضع الداكن.

</div>

---

<a id="english"></a>

# English

## Contents

1. [Overview](#overview)
2. [How the three parts fit together](#how-the-three-parts-fit-together)
3. [Features and services](#features-and-services)
4. [Security](#security)
5. [Screenshots](#screenshots)
6. [Running it step by step](#running-it-step-by-step)
7. [Demo accounts and test cards](#demo-accounts-and-test-cards)
8. [Connecting a store](#connecting-a-store)
9. [Connecting a bank](#connecting-a-bank)
10. [Production deployment](#production-deployment)
11. [Tests and quality](#tests-and-quality)
12. [Repository layout](#repository-layout)
13. [Release notes](#release-notes)

---

## Overview

**MatmoomPay** is a complete online payment gateway. Merchants, online stores and apps use it to accept card payments (Visa and Mastercard, debit and credit) and to manage their money, customers, subscriptions and settlements from one dashboard. Developers connect a store through a clear REST API in minutes.

**The project is modelled on how Thawani, the Omani payments company, works.** It follows the same model: checkout sessions, a hosted payment page, customers, saved cards, payment intents and refunds, down to the response envelope, status codes and webhook signing. A developer who has integrated Thawani before will find it familiar. MatmoomPay has its own name, design and copy. It is not affiliated with Thawani and does not use its name or logo.

- **Default currency:** Sudanese pound (SDG), whole pounds with no minor unit. The currency and its decimals are configurable.
- **Language:** Arabic first (right to left), with full English.
- **Two fully separate modes:** **test**, with fake cards and a sandbox bank, and **live**, which works once a bank is connected. Each mode has its own keys and data.

---

## How the three parts fit together

| Part | Folder | Stack | Role |
|---|---|---|---|
| **1. Gateway** | [`gateway/`](gateway) | Laravel 13, Livewire 4, Flux UI, Tailwind CSS 4, MySQL | Takes and processes payments: payment page, merchant dashboard, owner dashboard, API |
| **2. Demo web store** | [`store-web/`](store-web) | Laravel 13, SQLite | A realistic headphones shop whose customers pay through the gateway; also the backend for the mobile app |
| **3. Mobile app** | [`store-mobile/`](store-mobile) | Flutter, Riverpod, go_router, Dio | The same shop on iOS, Android and macOS, opening the payment page inside the app |

```mermaid
sequenceDiagram
    autonumber
    actor C as Customer
    participant M as Mobile app
    participant S as Store (store-web)
    participant G as Gateway
    participant B as Bank

    C->>S: Adds products to the cart and taps Pay
    Note over M,S: or from the app: POST /api/orders
    S->>G: POST /api/v1/checkout/session (secret key)
    G-->>S: session_id
    S-->>C: Redirect to /pay/{session_id}?key=publishable_key
    C->>G: Enters card details
    G->>B: Charge request
    B-->>G: Asks for OTP or 3-D Secure
    C->>G: Enters the code
    G-->>C: Redirect to the store's success_url
    G-)S: Signed webhook (checkout.completed)
    S->>G: Confirms the session status before accepting the order
    S-->>C: "Paid" and order confirmation
```

> **Ground rule:** the store never trusts the redirect to the success URL on its own, because anyone can open that URL by hand. An order is accepted only after the store asks the gateway for the session status, or after a signed webhook arrives.

---

## Features and services

### 1. Developer API (`/api/v1`)

| Service | Endpoints | What it does |
|---|---|---|
| Checkout sessions | `POST/GET /checkout/session`, `GET /checkout/session/{id}`, `GET /checkout/reference/{ref}`, `GET /checkout/invoice/{invoice}`, `POST /checkout/{id}/cancel` | Create a session with products, amounts and return URLs, then send the customer to the payment page |
| Customers | `POST/GET /customers`, `GET/DELETE /customers/{id}` | Register customers so their cards can be saved and reused |
| Saved cards | `GET /payment_methods`, `DELETE /payment_methods/{id}` | A customer's saved cards (a token only, never the card number) |
| Payment intents | `POST/GET /payment_intents`, `confirm`, `cancel` | Charge a saved card without the customer present |
| Payments | `GET /payments`, `GET /payments/{id}` | Search payments and read their details |
| Refunds | `POST/GET /refunds`, `GET /refunds/{id}` | Full or partial refunds, never more than was paid |
| Plans and subscriptions | `/plans`, `/subscriptions`, `cancel`, `invoices` | Weekly, monthly or yearly recurring billing |

- One response envelope everywhere: `{ success, code, description, data }`.
- Authentication with the `matmoom-api-key` header; each mode has a **secret** key (server) and a **publishable** key (payment page).
- `Idempotency-Key` support for safe retries.
- Pagination and filters on every list, and a free-form `metadata` field.

### 2. Hosted payment page

- A ready payment page at `/pay/{session_id}`, so the store never builds a card form.
- Mobile first, Arabic and English, showing the items, total and session expiry.
- Instant validation of card number (Luhn), expiry and CVC, with automatic formatting.
- OTP or 3-D Secure verification depending on the card, with limited attempts.
- One-tap payment with saved cards, and an option to save the card.
- **Bank-hosted payment page** mode: the customer types the card on the bank's own page, so the number never touches the gateway's servers.

### 3. Payment links and QR codes

- Fixed-amount or pay-what-you-want links, with no code.
- A QR code for every link and one-tap WhatsApp sharing.
- Optional phone and email collection, with an email receipt for the payer.

### 4. Merchant dashboard

| Section | What it offers |
|---|---|
| Overview | Available balance, today's and 30-day volume, success rate, daily chart, latest payments |
| Payments | Search and filters, payment details, full or partial refunds, CSV export |
| Checkout sessions | Every session and its status (open, paid, cancelled, expired) |
| Refunds and customers | Refund history, customers with their payments and saved cards |
| Payment links | Create and manage links and QR codes |
| Plans and subscriptions | Recurring plans, subscribers, cancellations |
| Balance and settlements | Ledger entries (payments, fees, refunds), payouts to the merchant's bank account, per-settlement statement export |
| Developers | API keys for both modes, webhook endpoints with a delivery log and manual resend |
| Business profile | Company details, bank account (IBAN), verification documents (commercial registration, owner ID, bank letter) |
| Team | Email invitations with five roles: owner, admin, developer, finance, viewer |

- One-click switch between test and live mode, with a clear banner in test mode.
- One user can belong to several businesses and switch between them.

### 5. Owner dashboard (platform admin)

- Overview of live volume, collected fees and amounts owed to merchants.
- **Merchant review:** read the profile and documents, then approve, reject, suspend or ban with a reason. A merchant is never approved for the first time before all three documents are accepted.
- User management: ban and unban users, archive merchants.
- All payments on the platform, plus creating and tracking merchant settlements.
- An append-only **audit log** of every sensitive action.

### 6. Notifications

- **Webhooks** for 19 events, including `checkout.completed`, `payment.succeeded`, `payment.failed`, `refund.succeeded`, `subscription.created`, `invoice.paid`, `settlement.paid`.
- Automatic retries, and an alert to the merchant when an endpoint keeps failing.
- **Email** to the merchant on payments, refunds, settlements and account status changes, plus payer receipts.

### 7. Subscriptions

- The first payment saves the card and starts the subscription; a job every 15 minutes bills renewals.
- Failed renewals are retried after 1, 3 and 5 days, then the subscription is cancelled.
- When the bank asks for verification, the invoice carries a link the merchant sends to the customer.

### 8. Fees and settlements

- Configurable fees: percentage (2.5% by default), fixed amount and minimum.
- A ledger of every payment, fee and refund, from which settlements are built.
- A configurable hold period before settlement (2 days by default).
- A reconciliation job every minute resolves payments with an unknown outcome and expires stale sessions.

---

## Security

| Area | Control |
|---|---|
| **Card data** | Card numbers and CVVs are never stored. Only an encrypted processor token plus the first 6 and last 4 digits |
| **PCI DSS scope** | In bank-hosted mode the customer types the card on the bank's page, which moves the payment page to the lighter SAQ A scope |
| **Keys and secrets** | Secret keys are stored hashed and shown once. IBANs and webhook signing secrets are encrypted at rest |
| **Data isolation** | Every query is scoped to the merchant and the mode. Another merchant's record returns 404 without revealing it exists |
| **Authorization** | Server-side checks on every action by team role; password confirmation before the API keys, bank account and team pages. The confirmation is re-checked on every Livewire update and cannot be skipped by changing request headers, and record pages re-authorize on every update |
| **Sessions and recovery** | Changing or resetting the password signs out every other session. Changing the email (the recovery channel) needs the current password and notifies the old address. Production email links use `APP_URL`, and the trusted host matches the app host exactly |
| **Team** | Invitations stop working when their sender is removed or demoted, and the sender is checked again on acceptance |
| **Settlement account** | An admin's verification is bound to the account they reviewed, so an IBAN swapped after the page opened is refused |
| **Double charges and races** | Row-level locks prevent double charges and over-refunds; `Idempotency-Key` makes retries safe. If a session is paid twice at the same moment, the second payment is refunded automatically |
| **Webhooks** | HMAC-SHA256 over `{body}-{timestamp}` in the `matmoom-signature` and `matmoom-timestamp` headers; events older than 5 minutes are rejected |
| **SSRF** | Webhooks to internal or private addresses are blocked, and the resolved IP is pinned after the check |
| **Bank responses** | Bank signature verified; reference, amount and currency must match the original request; each attempt completes once; responses older than 15 minutes are rejected |
| **Abuse limits** | Rate limits on login, payment, OTP, the API (per key and per IP), exports and invitations, plus sign-up, password reset, password confirmation, verification resends, test webhooks and each payment link. A whole IPv6 /64 counts as one address |
| **Email** | Test-mode receipts only reach the merchant's team, so the gateway cannot be used to mail strangers; merchant-written text is escaped in every email |
| **Background money flows** | One lock order for 3-D Secure completion, pending intents cancelled when a subscription ends, automatic refund of any renewal that lands after cancellation, and one retry chain per webhook |
| **Browser** | Nonce-based Content Security Policy, frame blocking on the payment page (clickjacking), HSTS, X-Content-Type-Options and Referrer-Policy headers |
| **Accounts** | Mandatory two-factor authentication for admins, passkeys, account bans, and a log of security events (2FA changes, password changes, passkeys) |
| **Uploads** | File type checked from the actual content, not the extension; private storage with random names; forced download after an authorization check. Temporary upload links only go to members who may upload documents, KYC types up to 5 MB, and abandoned temporary files are purged hourly |
| **CSV export** | Protection against spreadsheet formula injection |
| **Audit** | Append-only log of sensitive actions and a unique request ID on every response |
| **Production** | `php artisan gateway:check-production` checks 14 sensitive settings and fails if any is unsafe |

---

## Screenshots

> All data in the screenshots is fictional demo data. The interface is shown in Arabic; every screen is also available in English.

### Gateway: landing page and docs

| Landing (mobile) | API documentation | Sign in |
|---|---|---|
| <img src="docs/screenshots/gateway/01-landing-mobile.png" width="220"> | <img src="docs/screenshots/gateway/02-developer-docs.png" width="380"> | <img src="docs/screenshots/gateway/03-login.png" width="380"> |

### Merchant dashboard

<img src="docs/screenshots/merchant/01-dashboard.png" alt="Merchant dashboard" width="900">

| Payments | Payment details | Checkout sessions |
|---|---|---|
| <img src="docs/screenshots/merchant/02-payments.png" width="290"> | <img src="docs/screenshots/merchant/03-payment-details.png" width="290"> | <img src="docs/screenshots/merchant/04-checkouts.png" width="290"> |
| **Payment links** | **Link details and QR** | **Customers** |
| <img src="docs/screenshots/merchant/07-payment-links.png" width="290"> | <img src="docs/screenshots/merchant/08-payment-link-details.png" width="290"> | <img src="docs/screenshots/merchant/06-customers.png" width="290"> |
| **Plans** | **Subscriptions** | **Refunds** |
| <img src="docs/screenshots/merchant/09-plans.png" width="290"> | <img src="docs/screenshots/merchant/10-subscriptions.png" width="290"> | <img src="docs/screenshots/merchant/05-refunds.png" width="290"> |
| **Balance** | **Settlements** | **Team** |
| <img src="docs/screenshots/merchant/11-balance.png" width="290"> | <img src="docs/screenshots/merchant/12-settlements.png" width="290"> | <img src="docs/screenshots/merchant/13-team.png" width="290"> |
| **API keys** | **Webhooks** | **Business profile and bank account** |
| <img src="docs/screenshots/merchant/14-api-keys.png" width="290"> | <img src="docs/screenshots/merchant/15-webhooks.png" width="290"> | <img src="docs/screenshots/merchant/16-business-profile.png" width="290"> |

### Owner dashboard

<img src="docs/screenshots/admin/01-dashboard.png" alt="Owner dashboard" width="900">

| Merchants | Merchant review | Users |
|---|---|---|
| <img src="docs/screenshots/admin/02-merchants.png" width="290"> | <img src="docs/screenshots/admin/03-merchant-review.png" width="290"> | <img src="docs/screenshots/admin/04-users.png" width="290"> |
| **Payments** | **Settlements** | **Audit log** |
| <img src="docs/screenshots/admin/05-payments.png" width="290"> | <img src="docs/screenshots/admin/06-settlements.png" width="290"> | <img src="docs/screenshots/admin/07-audit-log.png" width="290"> |

### Demo store and the payment flow

| Shop | Cart | 1. Payment page | 2. Card details | 3. OTP | 4. Paid |
|---|---|---|---|---|---|
| <img src="docs/screenshots/store/01-shop-mobile.png" width="130"> | <img src="docs/screenshots/store/02-cart.png" width="200"> | <img src="docs/screenshots/payment/01-hosted-checkout-mobile.png" width="130"> | <img src="docs/screenshots/payment/02-card-details.png" width="200"> | <img src="docs/screenshots/payment/03-otp-verification-mobile.png" width="130"> | <img src="docs/screenshots/payment/04-order-paid.png" width="200"> |

### Mobile app

| Products | Cart | Order paid | Dark mode |
|---|---|---|---|
| <img src="docs/screenshots/mobile/products_light.png" width="200"> | <img src="docs/screenshots/mobile/cart_light.png" width="200"> | <img src="docs/screenshots/mobile/order_paid_light.png" width="200"> | <img src="docs/screenshots/mobile/products_grid_dark.png" width="200"> |

---

## Running it step by step

### Requirements

| Tool | Version |
|---|---|
| PHP | 8.4+ (usual Laravel extensions, plus sqlite and pdo_mysql) |
| Composer | 2 |
| Node.js and npm | 22 |
| MySQL | 8 (or SQLite for a quick try) |
| Flutter | 3.47+ (Dart 3.13+), mobile app only |

### 1. Gateway (`gateway/`)

```bash
cd gateway
composer setup                  # installs packages, creates .env and the app key, migrates, builds assets
php artisan migrate --seed      # prints the demo logins and a test secret key
php artisan serve --port=8000   # http://localhost:8000
php artisan queue:work          # second terminal: webhooks, email, renewals
php artisan schedule:work       # third terminal: renewals, reconciliation, cleanup
```

Or run everything together with `composer run dev`.

### 2. Demo store (`store-web/`)

```bash
cd store-web
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Sign in to the gateway as `merchant@matmoompay.test`, create a test secret key and publishable key under **Developers → API keys**, and put them in `store-web/.env`:

```dotenv
MATMOOM_BASE_URL=http://localhost:8000/api/v1
MATMOOM_PAY_URL=http://localhost:8000/pay
MATMOOM_SECRET_KEY=sk_test_...
MATMOOM_PUBLISHABLE_KEY=pk_test_...
MATMOOM_WEBHOOK_SECRET=        # from the Webhooks page, optional
```

```bash
php artisan serve --port=8001   # http://localhost:8001
```

To receive webhooks locally, add `http://localhost:8001/webhooks/matmoom` in the merchant dashboard, copy its signing secret into `MATMOOM_WEBHOOK_SECRET`, and set `GATEWAY_WEBHOOKS_ALLOW_PRIVATE_HOSTS=true` in `gateway/.env` (local development only; keep it `false` in production).

### 3. Mobile app (`store-mobile/`)

The app talks to the store, never to the gateway directly, so start both first.

```bash
cd store-mobile
flutter pub get
flutter run                                                     # iOS simulator or macOS (http://localhost:8001)
flutter run -d emulator-5554                                    # Android emulator (http://10.0.2.2:8001)
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8001 # real device on the same network
```

---

## Demo accounts and test cards

### Accounts (after `php artisan migrate --seed` in the gateway)

| Account | Email | Password | Notes |
|---|---|---|---|
| Platform owner (admin) | `admin@matmoompay.test` | `password` | `/admin`. The first visit asks to turn on two-factor authentication |
| Merchant | `merchant@matmoompay.test` | `password` | `/dashboard` for an approved business, Nile Aroma, with demo payments, links, a plan and a subscription |

The seeder also prints a **test secret key** (`sk_test_...`) you can use with the API right away. For local development only, `SECURITY_ADMIN_REQUIRES_2FA=false` skips the admin 2FA requirement.

### Test cards

Test mode only. Any future expiry date, any 3-digit CVC, and the **OTP is always `1234`**.

| Card number | Type | Result |
|---|---|---|
| `4242 4242 4242 4242` | Visa debit | Approved after OTP |
| `5123 4500 0000 0008` | Mastercard credit | Approved after OTP |
| `4000 0000 0000 0002` | Visa debit | Declined after OTP |
| `4456 5300 0000 1096` | Visa credit | 3-D Secure, approved |
| `4456 5300 0000 1104` | Visa credit | 3-D Secure, declined |

To try the **bank-hosted payment page** flow instead of the card form, set `GATEWAY_SANDBOX_FLOW=hosted` in `gateway/.env`; the payment page then sends the customer to the sandbox bank at `/sandbox-bank`.

---

## Connecting a store

`store-web/app/Services/MatmoomPay.php` is a complete, copy-ready example.

1. **Get your keys** from **Developers → API keys**. The secret key (`sk_...`) stays on your server; never ship it in a mobile app or browser code. The publishable key (`pk_...`) goes on the payment page URL.
2. **Create a checkout session** from your server:

   ```bash
   curl -X POST http://localhost:8000/api/v1/checkout/session \
     -H "matmoom-api-key: sk_test_..." \
     -H "Content-Type: application/json" \
     -H "Idempotency-Key: order-1001" \
     -d '{
       "client_reference_id": "order-1001",
       "mode": "payment",
       "products": [{ "name": "Karkade 1 kg", "quantity": 2, "unit_amount": 3200 }],
       "success_url": "https://your-store.test/orders/1001/return",
       "cancel_url": "https://your-store.test/cart",
       "metadata": { "Customer name": "Amna Osman" }
     }'
   ```

   Amounts are integers in the currency's smallest unit. With SDG (no minor unit), `3200` means SDG 3,200.
3. **Redirect the customer** to `http://localhost:8000/pay/{session_id}?key={publishable_key}`.
4. **Confirm before fulfilling.** When the customer lands on `success_url`, call `GET /api/v1/checkout/session/{session_id}` and accept the order only when `data.payment_status` is `paid`.
5. **Receive webhooks** (recommended). Add your endpoint under **Developers → Webhooks** and keep the signing secret. Verify each event:

   ```php
   $body      = $request->getContent();
   $timestamp = $request->header('matmoom-timestamp');
   $signature = $request->header('matmoom-signature');

   $valid = ctype_digit($timestamp)
       && abs(time() - (int) $timestamp) <= 300
       && hash_equals(hash_hmac('sha256', $body.'-'.$timestamp, $webhookSecret), $signature);
   ```

   Handle the event by its `event` field (for example `checkout.completed`), return `200` quickly, and ignore duplicates: an event can arrive more than once.

**Mobile apps:** the app never holds the secret key or calls the gateway. It asks your server to create the order (`POST /api/orders`); the server creates the session and returns a `payment_url` and an order access token; the app opens the URL in a WebView and watches for the return URLs. That is exactly how `store-mobile` works with `store-web`.

---

## Connecting a bank

A real acquirer plugs in without changing any other part of the system. Test mode always uses the sandbox processor; live mode refuses every payment until a bank is configured (`GATEWAY_LIVE_PROCESSOR=unconfigured`).

**Interfaces** (`gateway/app/Services/Payments/Contracts`):
- `PaymentProcessor`: `tokenize`, `charge`, `completeChallenge`, `refund`, `retrieve`, for the on-page card form, saved cards and refunds.
- `HostedPaymentPageProcessor`: `startHostedPayment` (registers the payment with the bank and returns its page URL) and `completeHostedPayment` (verifies the bank's signed response). This is the recommended path, since the card is typed on the bank's page.

**Steps:**

1. From the bank, get the payment API documentation, test environment credentials (merchant ID, keys, TLS certificates if any), the request/response signing scheme, and the return URLs to register.
2. Create a class in `gateway/app/Services/Payments/Processors/`, for example `BankXProcessor`, implementing both interfaces:
   - `startHostedPayment`: send the reference, amount, currency and the `pay/return/{attempt}` return URL to the bank and return its page URL.
   - `completeHostedPayment`: verify the bank's signature and throw `InvalidProcessorCallback` on any problem. The gateway itself matches reference, amount and currency and prevents double completion.
   - `refund` / `charge`: call the bank's refund and token-charge APIs if supported.
3. Register it in `PaymentProcessorResolver`:

   ```php
   return match (config('gateway.processors.live')) {
       'bank_x' => $this->container->make(BankXProcessor::class),
       default  => $this->container->make(UnconfiguredProcessor::class),
   };
   ```

4. Add the bank's credentials to `config/services.php` and the environment, then set `GATEWAY_LIVE_PROCESSOR=bank_x` in production.
5. Test against the bank's sandbox, then run `php artisan gateway:check-production`.

The payment page, API, settlements, subscriptions and webhooks keep working unchanged. `SandboxHostedProcessor` and `/sandbox-bank` are a complete reference implementation (signed redirect out, signed response back).

---

## Production deployment

1. Copy `gateway/.env.production.example` to the server as `.env` and fill in every empty value.
2. On every deploy:

   ```bash
   php artisan gateway:check-production
   php artisan migrate --force
   php artisan optimize
   npm ci && npm run build
   ```

3. Keep a queue worker running (`php artisan queue:work` under Supervisor) and add the scheduler to cron:

   ```cron
   * * * * * cd /path/to/gateway && php artisan schedule:run >> /dev/null 2>&1
   ```

4. Make sure of HTTPS, `APP_DEBUG=false`, `SESSION_ENCRYPT=true`, secure cookies, Redis for sessions and queues, a mail provider, and `TRUSTED_PROXIES` behind a load balancer.
5. Before taking real money: connect the bank, complete PCI DSS requirements with the bank, and commission an external penetration test.

---

## Tests and quality

| Part | Command | Current status |
|---|---|---|
| Gateway | `cd gateway && php artisan test --compact` | 319 tests passing (991 assertions) |
| Gateway | `vendor/bin/phpstan analyse`, `vendor/bin/pint --test` | Larastan level 7, Pint formatting |
| Store | `cd store-web && php artisan test --compact` | 21 tests passing |
| App | `cd store-mobile && flutter analyze && flutter test` | Strict analysis, unit, widget and golden tests |

GitHub Actions ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)) runs the three test suites on every push.

---

## Repository layout

```
.
├── gateway/                  Payment gateway (Laravel)
│   ├── app/Actions/          Business logic: payments, refunds, settlements, subscriptions...
│   ├── app/Services/Payments/ Processor interfaces, sandbox processor, bank integration point
│   ├── app/Http/Controllers/Api/V1/  REST API
│   ├── resources/views/pages/        Merchant, owner and payment pages
│   ├── lang/                 Arabic translations
│   └── tests/                Pest tests
├── store-web/                Demo store (Laravel)
│   └── app/Services/MatmoomPay.php   Gateway integration example
├── store-mobile/             Mobile app (Flutter)
│   └── lib/features/         Products, cart, checkout, orders
├── docs/screenshots/         Screenshots
└── .github/workflows/        Continuous integration
```

---

## Release notes

This section is updated with every new push; the latest release goes on top.

### 1.1.0 — 17 November 2026 (security hardening)

- Full source security audit of the gateway (39 coverage units, two independent verifications per finding); every result was fixed: 8 confirmed vulnerabilities and 13 needs-validation issues.
- Highlights: closed a Livewire password-confirmation bypass via `Accept: application/json`, put the team page behind password confirmation, required the current password to change the email, and signed out other sessions on password change.
- Removed members can no longer read through open pages, their pending invitations are withdrawn, and settlement-account verification is bound to what the admin reviewed.
- Anchored the trusted host, added rate limits on email and password-confirmation routes, restricted and purged temporary uploads, and fixed money paths in renewals and 3-D Secure completion.
- New regression tests in `gateway/tests/Feature/SecuritySafeguardsTest.php`.

### 1.0.0 — 17 November 2026

- First release of the repository, bringing together the gateway, the demo web store and the mobile app.
- Gateway: full API, hosted payment page and bank-hosted flow, payment links and QR codes, subscriptions, settlements, merchant dashboard, owner dashboard with user management, bans and archiving, and an audit log.
- Demo store: a web shop paying through the gateway, with an API for the mobile app and webhook handling.
- Mobile app: five screens (products, cart, checkout, result, my orders) in Arabic and English, with dark mode.
