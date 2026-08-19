# مرجع API — إشعارات الزبون

> مرجع لمطوّر تطبيق الموبايل. كل الردود في هذا الملف **ملتقَطة فعلياً من الكود الحالي** بنداءات حقيقية على الخادم، لا مكتوبة يدوياً.
>
> آخر تحديث: ١٦ آب ٢٠٢٦.

---

## نظرة عامة

للزبون **قناتان** يستلم بهما الإشعار، وكلاهما يحمل **نفس الحمولة (`data`)**:

| القناة | متى | ماذا يحمل |
|---|---|---|
| **إشعار فوري (Push)** عبر OneSignal | لحظة وقوع الحدث والتطبيق مغلق/بالخلفية | `title` · `body` · نفس `data` |
| **مركز الإشعارات** (هذي المسارات) | يفتحه الزبون ليقرأ ما فاته | كل الإشعارات مخزّنة، مقروءة وغير مقروءة |

> **المهم:** الحقل `data` **متطابق** في القناتين، فتقدر تعتمد **معالِج تنقّل واحداً** يفتح الشاشة الصحيحة سواء نقر الزبون على الإشعار الفوري أو على عنصر بالقائمة.

كل المسارات هنا **تحتاج توكن** 🔒 (`Authorization: Bearer <token>`)، وكلها مقصورة على إشعارات صاحب التوكن فقط — لا يرى إشعارات غيره أبداً.

---

## ١. تكامل OneSignal — إلزامي لمطوّر التطبيق ⚠️

**بدون هذا القسم لا يصل أي إشعار فوري.** مركز الإشعارات (المسارات ٤–٧) يشتغل بلا OneSignal، لكن الإشعار **الفوري** يعتمد عليه كلياً.

### كيف يعرف OneSignal أن الجهاز أندرويد أو آيفون؟

**الخادم لا يعرف نوع الجهاز ولا يحتاج.** الخادم يرسل إلى `external_id` فقط ويقول «ابعث لهذا المستخدم»، و**OneSignal نفسه** يميّز المنصّة ويوصّل عبر القناة الصحيحة:

```
الخادم → external_id = "user:5" + target_channel: push
              ↓
OneSignal → يعرف من SDK داخل التطبيق: أندرويد (FCM) أو آيفون (APNs)
              ↓
          يوصّل للمنصّة الصحيحة تلقائياً
```

SDK تبع OneSignal داخل التطبيق هو الذي يسجّل الجهاز عند OneSignal ويبلّغه بمنصّته وبرمز الدفع الخاص به (APNs للآيفون، FCM للأندرويد). فلا يخزّن الخادم أي `player_id` ولا device token — **لا يوجد مسار «تسجيل جهاز» في هذا الـAPI، ولا يجب أن يكون.**

### ما الذي يجب أن يفعله التطبيق

| الخطوة | الإجراء |
|---|---|
| **إعداد لمرة واحدة** | تضمين OneSignal SDK (Android + iOS) بمعرّف التطبيق `App ID` |
| **أول تشغيل** | طلب إذن الإشعارات — إلزامي على آيفون، وعلى أندرويد ١٣+ (`POST_NOTIFICATIONS`) |
| **بعد تسجيل الدخول** | `OneSignal.login("user:" + userId)` — يربط الجهاز بـ`external_id` |
| **عند تسجيل الخروج** | `OneSignal.logout()` — يفصل الجهاز عن الحساب |

> 🔴 **صيغة `external_id` حرجة:** الخادم يرسل إلى **`"user:" + id`** بالضبط (مثال: `user:5`). التطبيق **يجب** أن يستدعي `OneSignal.login("user:" + userId)` بنفس الصيغة تماماً. أي صيغة أخرى (كإرسال الـ`id` وحده) تجعل الإشعار **لا يصل**، لأن المعرّف لا يتطابق.

### المفاتيح — أيّها في التطبيق وأيّها في الخادم

| المفتاح | مكانه | ملاحظة |
|---|---|---|
| **OneSignal App ID** | التطبيق (عام) | آمن داخل التطبيق — يُستعمل لتهيئة الـSDK |
| **REST API Key** | **الخادم فقط** | سرّي — يبقى في إعدادات اللوحة، لا يوضع في التطبيق أبداً |

> إعداد المنصّات نفسه (شهادة Apple Push للآيفون، ومفتاح Firebase/FCM للأندرويد) يتمّ **مرة واحدة في لوحة OneSignal**، لا في كود التطبيق ولا الخادم.

### قراءة الحمولة عند وصول الإشعار

الإشعار الفوري يحمل نفس `data` المذكور بالأسفل، ويصل عبر OneSignal في `additionalData`. اقرأه لفتح الشاشة الصحيحة (انظر §٩ التنقّل). عالِج حالتَي **التطبيق مفتوح** (foreground) و**مغلق/بالخلفية** (نقر على الإشعار).

### كيف يرسل الخادم (للعلم فقط)

| النوع | كيف يستهدف | من يستقبل |
|---|---|---|
| إشعار طلب / تغيير فني | `external_id = "user:<id>"` | جهاز/أجهزة صاحب الحساب فقط |
| مقال جديد (مدوّنة) | `included_segments: ["Subscribed Users"]` | كل الأجهزة المشتركة (بثّ عام) |

---

## ٢. أنواع الإشعارات

| `type` | متى يصل | المفتاح الذي يفتح الشاشة |
|---|---|---|
| `order_status` | تغيّرت حالة طلبك (استلام، تأكيد، تعيين فني، كشف، إنجاز، إلغاء) | `order_id` |
| `technician_reassigned` | تبدّل الفني المكلّف بطلبك | `order_id` |
| `blog_post` | نُشر مقال جديد بالمدوّنة | `blog_post_id` |

---

## ٣. حقول عنصر الإشعار

كل إشعار في القائمة (وفي رد «تعليم كمقروء») يحمل هذي الحقول:

| الحقل | النوع | الوصف |
|---|---|---|
| `id` | رقم | معرّف الإشعار — تستعمله في «تعليم كمقروء» |
| `title` | نص | العنوان، جاهز للعرض |
| `body` | نص | النص، جاهز للعرض |
| `type` | نص | أحد الأنواع الثلاثة أعلاه |
| `is_read` | منطقي | `true` إن كان مقروءاً |
| `read_at` | نص/`null` | وقت القراءة ISO8601، أو `null` |
| `order_id` | رقم/`null` | لإشعارات الطلب — يفتح صفحة الطلب |
| `order_number` | نص/`null` | رقم الطلب المعروض (`HS-…`) |
| `status` | نص/`null` | حالة الطلب الجديدة (لإشعارات الطلب) |
| `blog_post_id` | رقم/`null` | لإشعار المقال — يفتح صفحة المقال |
| `data` | كائن | **الحمولة الكاملة** كما وصلت بالإشعار الفوري (انظر أدناه) |
| `created_at` | نص | وقت الإنشاء ISO8601 |

> الحقول `order_id` / `order_number` / `status` / `blog_post_id` هي **اختصارات مسطّحة** من `data` لراحتك. و`data` تحمل الحمولة كاملة لأي نوع.

### شكل `data` حسب النوع

```jsonc
// order_status
{ "order_id": 41, "order_number": "HS-260816-0007", "from_status": "confirmed", "to_status": "assigned" }

// technician_reassigned
{ "order_id": 41, "order_number": "HS-260816-0007", "to_status": "assigned",
  "technician_id": 4, "previous_technician_id": 2 }

// blog_post
{ "blog_post_id": 7, "title": "خمس علامات تدل على تسريب في أنابيب المطبخ" }
```

---

## ٤. `GET /customer/notifications` 🔒 — قائمة الإشعارات

الأحدث أولاً، مقسّمة على صفحات.

**المعاملات (Query):**

| المعامل | الوصف |
|---|---|
| `unread=1` | غير المقروءة فقط |
| `per_page` | عدد العناصر بالصفحة (افتراضي ٢٠ · الحد الأقصى ١٠٠) |
| `page` | رقم الصفحة |

**الرد `200`:**
```json
{
  "success": true,
  "message": "Notifications retrieved successfully",
  "data": [
    {
      "id": 43,
      "title": "مقال جديد",
      "body": "خمس علامات تدل على تسريب في أنابيب المطبخ",
      "type": "blog_post",
      "is_read": true,
      "read_at": "2026-08-16T16:06:58+03:00",
      "order_id": null,
      "order_number": null,
      "status": null,
      "blog_post_id": 7,
      "data": { "title": "خمس علامات تدل على تسريب في أنابيب المطبخ", "blog_post_id": 7 },
      "created_at": "2026-08-16T19:06:58+03:00"
    },
    {
      "id": 42,
      "title": "تم تغيير الفني المكلّف بطلبك",
      "body": "الفني عمر جاسم سيتولى طلبك رقم HS-260816-0007 بدلاً من حيدر كاظم",
      "type": "technician_reassigned",
      "is_read": false,
      "read_at": null,
      "order_id": 41,
      "order_number": "HS-260816-0007",
      "status": "assigned",
      "blog_post_id": null,
      "data": {
        "order_id": 41,
        "to_status": "assigned",
        "order_number": "HS-260816-0007",
        "technician_id": 4,
        "previous_technician_id": 2
      },
      "created_at": "2026-08-16T19:06:58+03:00"
    },
    {
      "id": 41,
      "title": "تم تعيين فني لطلبك",
      "body": "الفني حيدر كاظم سيتولى طلبك رقم HS-260816-0007",
      "type": "order_status",
      "is_read": false,
      "read_at": null,
      "order_id": 41,
      "order_number": "HS-260816-0007",
      "status": "assigned",
      "blog_post_id": null,
      "data": { "order_id": 41, "to_status": "assigned", "from_status": "confirmed", "order_number": "HS-260816-0007" },
      "created_at": "2026-08-16T19:06:58+03:00"
    },
    {
      "id": 40,
      "title": "تم استلام طلبك",
      "body": "استلمنا طلبك رقم HS-260816-0007 وسنتواصل معك قريباً",
      "type": "order_status",
      "is_read": false,
      "read_at": null,
      "order_id": 41,
      "order_number": "HS-260816-0007",
      "status": "pending",
      "blog_post_id": null,
      "data": { "order_id": 41, "to_status": "pending", "from_status": null, "order_number": "HS-260816-0007" },
      "created_at": "2026-08-16T19:06:58+03:00"
    }
  ],
  "meta": { "current_page": 1, "per_page": 4, "total": 4, "last_page": 1 }
}
```

**بـ`?unread=1`:** نفس الشكل، لكن العناصر المقروءة محذوفة و`meta.total` يعكس عدد غير المقروءة فقط.

**لا إشعارات `200`:**
```json
{
  "success": true,
  "message": "Notifications retrieved successfully",
  "data": [],
  "meta": { "current_page": 1, "per_page": 20, "total": 0, "last_page": 1 }
}
```

---

## ٥. `GET /customer/notifications/unread-count` 🔒 — عدّاد الجرس

للنقطة الحمراء على أيقونة الجرس. خفيف — استدعِه عند فتح التطبيق ودورياً.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Unread count retrieved successfully",
  "data": { "unread_count": 3 }
}
```

---

## ٦. `POST /customer/notifications/{id}/read` 🔒 — تعليم إشعار كمقروء

بلا جسم طلب. يرجّع الإشعار بعد تحديثه.

**الرد `200`:**
```json
{
  "success": true,
  "message": "تم تعليم الإشعار كمقروء",
  "data": {
    "id": 43,
    "title": "مقال جديد",
    "body": "خمس علامات تدل على تسريب في أنابيب المطبخ",
    "type": "blog_post",
    "is_read": true,
    "read_at": "2026-08-16T16:06:58+03:00",
    "order_id": null,
    "order_number": null,
    "status": null,
    "blog_post_id": 7,
    "data": { "title": "خمس علامات تدل على تسريب في أنابيب المطبخ", "blog_post_id": 7 },
    "created_at": "2026-08-16T19:06:58+03:00"
  }
}
```

> **آمن للتكرار:** تعليم إشعار مقروء أصلاً يرجّع `200` بنفس `read_at` الأول (لا يتغيّر).

**إشعار غير موجود أو ليس لك `404`:**
```json
{ "success": false, "message": "Resource not found", "errors": [] }
```

---

## ٧. `POST /customer/notifications/read-all` 🔒 — تعليم الكل كمقروء

بلا جسم طلب. يرجّع عدد الإشعارات التي تغيّرت فعلاً.

**الرد `200`:**
```json
{
  "success": true,
  "message": "تم تعليم كل الإشعارات كمقروءة",
  "data": { "marked": 3 }
}
```

**وما في شيء غير مقروء `200`:**
```json
{
  "success": true,
  "message": "تم تعليم كل الإشعارات كمقروءة",
  "data": { "marked": 0 }
}
```

---

## ٨. أخطاء عامة

**بلا توكن / توكن منتهٍ `401`:**
```json
{ "success": false, "message": "Unauthorized", "errors": [] }
```
> كأي `401`: امسح التوكن وارجع لشاشة الدخول.

---

## ٩. التنقّل عند النقر (Deep Linking)

معالِج واحد يخدم الإشعار الفوري وعنصر القائمة (نفس `data`):

```
type == "order_status"          → افتح الطلب  data.order_id  (أو الحقل المسطّح order_id)
type == "technician_reassigned" → افتح الطلب  data.order_id
type == "blog_post"             → افتح المقال data.blog_post_id
```

بعد فتح الشاشة، نادِ `POST /notifications/{id}/read` (لعنصر القائمة) لتحديث العدّاد.

---

## ١٠. مرجع سريع

| النقطة | الطريقة | الوصول | الوصف |
|---|---|---|---|
| `/customer/notifications` | `GET` | 🔒 | القائمة (يقبل `?unread=1&per_page=&page=`) |
| `/customer/notifications/unread-count` | `GET` | 🔒 | عدّاد غير المقروء |
| `/customer/notifications/{id}/read` | `POST` | 🔒 | تعليم إشعار كمقروء |
| `/customer/notifications/read-all` | `POST` | 🔒 | تعليم الكل كمقروء |

**الخلاصة:** الإشعار يصل بقناتين (فوري + قائمة) بنفس `data`. استعمل `order_id` أو `blog_post_id` لفتح الشاشة الصحيحة، وحدّث حالة القراءة عبر نقطتَي «التعليم».
