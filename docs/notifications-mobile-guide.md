# دليل الإشعارات — لمطوّر تطبيق الموبايل

> دليل عملي خطوة بخطوة لتشغيل الإشعارات في تطبيق **Hoame Service**. كل الردود مأخوذة من الخادم فعلياً.
>
> للمرجع التفصيلي لكل نقطة ورداتها: `docs/customer-notifications-api.md`.
>
> آخر تحديث: ١٩ آب ٢٠٢٦.

---

## الصورة الكبيرة (اقرأها أولاً)

الزبون يستلم الإشعار عبر **قناتين**، وكلاهما يحمل **نفس الحقل `data`**:

```
حدث على الخادم (تغيّر حالة طلب، تعيين فني، مقال جديد)
          │
          ├──▶  ①  Push فوري  ──▶  OneSignal  ──▶  جهاز الزبون (والتطبيق مغلق أو مفتوح)
          │
          └──▶  ②  يُخزَّن في مركز الإشعارات  ──▶  يقرأه التطبيق من الـAPI لاحقاً
```

| القناة | تحتاج | لمن |
|---|---|---|
| ① **Push فوري** | تكامل OneSignal SDK (القسم ١) | تنبيه لحظي |
| ② **مركز الإشعارات** | نداءات API بالتوكن (القسم ٤) | سِجِلّ يُقرأ ويُعلَّم مقروءاً |

> **القاعدة الذهبية:** الحقل `data` **متطابق** في القناتين. اكتب **معالِج تنقّل واحداً** يفتح الشاشة الصحيحة سواء نقر الزبون على الـPush أو على عنصر في قائمة الإشعارات.

---

## ✅ قائمة التشغيل السريعة (خمس خطوات)

1. ضمّن **OneSignal SDK** في التطبيق بـ **App ID**: `01239609-a52b-441b-b31d-895874f8503e`
2. عند أول تشغيل: **اطلب إذن الإشعارات** (إلزامي على iOS، وعلى Android 13+).
3. **بعد تسجيل الدخول** مباشرة: `OneSignal.login("user:" + userId)` ← **الصيغة حرجة**.
4. **عند تسجيل الخروج**: `OneSignal.logout()`.
5. اكتب **معالِج نقر** واحداً يقرأ `data` ويفتح الشاشة (القسم ٣)، ونادِ مركز الإشعارات لتحديث العدّاد (القسم ٤).

بعد هذي الخطوات، جرّب: غيّر حالة طلب من لوحة الإدارة ← يصل Push خلال ثوانٍ.

---

## ١. تكامل OneSignal — إلزامي

### كيف يُعرَف نوع الجهاز (Android / iPhone)؟

**الخادم لا يعرف ولا يحتاج.** يرسل إلى `external_id` فقط، و**OneSignal SDK داخل تطبيقك** هو الذي يسجّل الجهاز ويميّز منصّته (APNs للآيفون، FCM للأندرويد) ويوصّل عبر القناة الصحيحة تلقائياً. لا يوجد «تسجيل جهاز» في الـAPI، ولا يجب أن يكون.

### الخطوات

| الخطوة | الإجراء |
|---|---|
| إعداد لمرة واحدة | OneSignal SDK بـ App ID أعلاه |
| أول تشغيل | طلب إذن الإشعارات (`requestPermission`) |
| **بعد الدخول** | `OneSignal.login("user:" + userId)` |
| عند الخروج | `OneSignal.logout()` |

### 🔴 صيغة `external_id` — أهم نقطة

الخادم يرسل حرفياً إلى **`"user:" + id`** (مثال: `user:16`). تطبيقك **يجب** أن يربط الجهاز بنفس الصيغة تماماً:

```txt
OneSignal.login("user:" + userId)     ✅   →   user:16
OneSignal.login(userId)               🔴   →   16         (لا يطابق، لا يصل)
OneSignal.login("User:" + userId)     🔴   →   User:16    (حالة أحرف مختلفة)
```

أي انحراف (رقم وحده، مسافة، حرف كبير) يجعل OneSignal لا يجد جهازاً مطابقاً، فالإشعار لا يصل.

### المفاتيح

| المفتاح | مكانه |
|---|---|
| **OneSignal App ID** `01239609-…` | داخل التطبيق (عام، آمن) |
| **REST API Key** | الخادم فقط — لا يوضع في التطبيق أبداً |

> إعداد المنصّات (شهادة Apple Push للآيفون، مفتاح Firebase/FCM للأندرويد) يتمّ مرة واحدة في **لوحة OneSignal**، لا في كود التطبيق.

---

## ٢. أنواع الإشعارات

| `type` | متى يصل | المفتاح الذي يفتح الشاشة |
|---|---|---|
| `order_status` | تغيّرت حالة طلبك (استلام · تأكيد · تعيين فني · كشف · إنجاز · إلغاء) | `order_id` |
| `technician_reassigned` | تبدّل الفني المكلّف بطلبك | `order_id` |
| `blog_post` | نُشر مقال جديد بالمدوّنة | `blog_post_id` |

### شكل `data` لكل نوع

```jsonc
// order_status
{ "order_id": 41, "order_number": "HS-260819-0007", "from_status": "confirmed", "to_status": "assigned" }

// technician_reassigned  — to_status = حالة الطلب الحالية (لا يتغيّر بالتبديل)
{ "order_id": 41, "order_number": "HS-260819-0007", "to_status": "inspected",
  "technician_id": 4, "previous_technician_id": 2 }

// blog_post
{ "blog_post_id": 7, "title": "خمس علامات على تسريب أنابيب المطبخ" }
```

> نفس هذا `data` يصل في الـPush (داخل `additionalData`) وفي عنصر القائمة — فمعالِج واحد يكفي.

---

## ٣. استقبال الـPush ومعالجة النقر (Deep Linking)

الإشعار الفوري يحمل `title` و`body` (جاهزان للعرض) و`data`. تعامل مع حالتين:

- **التطبيق مفتوح (foreground):** اعرض تنبيهاً داخلياً بسيطاً، وحدّث عدّاد الجرس.
- **مغلق/بالخلفية:** النظام يعرض الإشعار؛ عند نقره، افتح الشاشة حسب `data`.

**معالِج النقر الموحّد:**

```txt
اقرأ data من الإشعار (Push: additionalData ، القائمة: الحقل data)

switch (data.type):
  "order_status"           → افتح صفحة الطلب   بـ data.order_id
  "technician_reassigned"  → افتح صفحة الطلب   بـ data.order_id
  "blog_post"              → افتح صفحة المقال  بـ data.blog_post_id

ثم (لعنصر القائمة) نادِ POST /notifications/{id}/read لتحديث العدّاد.
```

> `data` كائن نصّي — تأكّد أن `order_id` / `blog_post_id` تُقرأ كأرقام حسب SDK المستعمَل.

---

## ٤. مركز الإشعارات (نداءات الـAPI)

كل هذي **تحتاج توكن** 🔒: `Authorization: Bearer <token>`، ومقصورة على إشعارات صاحب التوكن.

**الأساس:** `https://servix.diyala.org/api/v1`

### 4.1 `GET /customer/notifications` — القائمة

يقبل: `?unread=1` (غير المقروءة فقط) · `?per_page=20` (حد أقصى ١٠٠) · `?page=1`. الأحدث أولاً.

```json
{
  "success": true,
  "message": "Notifications retrieved successfully",
  "data": [
    {
      "id": 47,
      "title": "تم تعيين فني لطلبك",
      "body": "الفني حيدر كاظم سيتولى طلبك رقم HS-260819-0007",
      "type": "order_status",
      "is_read": false,
      "read_at": null,
      "order_id": 41,
      "order_number": "HS-260819-0007",
      "status": "assigned",
      "blog_post_id": null,
      "data": { "order_id": 41, "order_number": "HS-260819-0007", "from_status": "confirmed", "to_status": "assigned" },
      "created_at": "2026-08-19T19:20:00+03:00"
    },
    {
      "id": 48,
      "title": "مقال جديد",
      "body": "خمس علامات على تسريب أنابيب المطبخ",
      "type": "blog_post",
      "is_read": false,
      "read_at": null,
      "order_id": null,
      "order_number": null,
      "status": null,
      "blog_post_id": 7,
      "data": { "blog_post_id": 7, "title": "خمس علامات على تسريب أنابيب المطبخ" },
      "created_at": "2026-08-19T19:20:00+03:00"
    }
  ],
  "meta": { "current_page": 1, "per_page": 2, "total": 2, "last_page": 1 }
}
```

**حقول العنصر:**

| الحقل | الوصف |
|---|---|
| `id` | معرّف الإشعار — لـ«تعليم كمقروء» |
| `title` · `body` | جاهزان للعرض |
| `type` | أحد الأنواع الثلاثة |
| `is_read` · `read_at` | حالة القراءة |
| `order_id` · `order_number` · `status` | اختصارات مسطّحة لإشعارات الطلب |
| `blog_post_id` | لإشعار المقال |
| `data` | الحمولة الكاملة (نفس الـPush) |
| `created_at` | ISO8601 |

### 4.2 `GET /customer/notifications/unread-count` — عدّاد الجرس

```json
{ "success": true, "message": "Unread count retrieved successfully", "data": { "unread_count": 2 } }
```

استدعِه عند فتح التطبيق ودورياً، وبعد كل «تعليم كمقروء».

### 4.3 `POST /customer/notifications/{id}/read` — تعليم واحد

بلا جسم طلب. يرجّع الإشعار محدَّثاً (`is_read: true`). **آمن للتكرار** — تعليم مقروء أصلاً يرجّع `200` بنفس `read_at`.

```json
{
  "success": true,
  "message": "تم تعليم الإشعار كمقروء",
  "data": { "id": 47, "type": "order_status", "is_read": true, "read_at": "2026-08-19T19:25:00+03:00", "…": "بقية الحقول" }
}
```

**إشعار غير موجود أو ليس لك → `404`:** `{ "success": false, "message": "Resource not found", "errors": [] }`

### 4.4 `POST /customer/notifications/read-all` — تعليم الكل

بلا جسم طلب. يرجّع عدد ما تغيّر فعلاً.

```json
{ "success": true, "message": "تم تعليم كل الإشعارات كمقروءة", "data": { "marked": 2 } }
```

---

## ٥. أخطاء عامة

**بلا توكن / توكن منتهٍ → `401`:**
```json
{ "success": false, "message": "Unauthorized", "errors": [] }
```
> كأي `401`: امسح التوكن وارجع لشاشة الدخول.

---

## ٦. استكشاف الأخطاء (لو ما وصل Push)

جرّبها بالترتيب:

| # | تحقّق | كيف |
|---|---|---|
| ١ | **الجهاز مشترك؟** | افتح لوحة OneSignal → Subscriptions، تأكّد ظهور الجهاز |
| ٢ | **الـexternal_id صحيح؟** | تأكّد أن `OneSignal.login("user:"+id)` نُفِّذ بعد الدخول، بالصيغة الحرفية `user:<id>` |
| ٣ | **الإذن مُنِح؟** | على iOS وAndroid 13+ لازم يقبل الزبون الإذن |
| ٤ | **مركز الإشعارات فيه العنصر؟** | نادِ `GET /customer/notifications` — لو موجود والـPush لا يصل، فالمشكلة بالجهاز/الإذن؛ لو غير موجود، فالمشكلة عند الخادم |
| ٥ | **جرّب Push يدوي** من لوحة OneSignal لجهازك | لو وصل، فالتكامل سليم والمشكلة بالاستهداف (خطوة ٢) |

> **تمييز مفيد:** «الإشعار في مركز التطبيق موجود لكن Push ما وصل» = مشكلة جهاز/إذن/اشتراك. «غير موجود بالمركز أصلاً» = مشكلة عند الخادم، بلّغ فريق الباك.

---

## ٧. مرجع سريع

| النقطة | الطريقة | الوصول | الوصف |
|---|---|---|---|
| `/customer/notifications` | GET | 🔒 | القائمة (`?unread=1&per_page=&page=`) |
| `/customer/notifications/unread-count` | GET | 🔒 | عدّاد غير المقروء |
| `/customer/notifications/{id}/read` | POST | 🔒 | تعليم إشعار كمقروء |
| `/customer/notifications/read-all` | POST | 🔒 | تعليم الكل كمقروء |

**تذكير أخير:** الإشعار يصل بقناتين بنفس `data`. اربط الجهاز بـ`OneSignal.login("user:"+id)` بعد الدخول، واستعمل `order_id` أو `blog_post_id` لفتح الشاشة الصحيحة.
