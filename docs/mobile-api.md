# Hoame Service — واجهة تطبيق الزبون (API)

**الإصدار:** v1 · **الأساس:** `https://<domain>/api/v1`
**كل الأمثلة أدناه ملتقطة من السيرفر فعلياً، مو مكتوبة يدوياً.**

---

## ١. الأساسيات

### الرؤوس (Headers)

كل طلب:
```
Accept: application/json
```
مع رفع الملفات:
```
Content-Type: multipart/form-data
```
غير ذلك:
```
Content-Type: application/json
```
والمسارات المحمية:
```
Authorization: Bearer <token>
```

### شكل الرد — موحّد دائماً

**نجاح:**
```json
{ "success": true, "message": "نص عربي جاهز للعرض", "data": { } }
```

**فشل:**
```json
{ "success": false, "message": "نص عربي جاهز للعرض", "errors": { "الحقل": ["الرسالة"] } }
```

**قائمة مقسّمة على صفحات** — تضيف `meta`:
```json
{ "success": true, "message": "...", "data": [ ], "meta": { "current_page": 1, "per_page": 15, "total": 8, "last_page": 1 } }
```

> **مهم:** `message` مكتوبة بالعربي وجاهزة للعرض مباشرة للمستخدم. لا تكتب رسائل بالتطبيق.

### رموز الحالة

| الرمز | المعنى | تصرّف التطبيق |
|---|---|---|
| `200` | نجاح | — |
| `201` | أُنشئ | — |
| `401` | غير مصرّح — توكن مفقود أو منتهٍ | امسح التوكن وارجع لشاشة الدخول |
| `403` | ممنوع | اعرض `message` |
| `404` | غير موجود | اعرض `message` |
| `409` | تعارض — العملية نُفّذت مسبقاً (نقرة مزدوجة) | اعرض `message`، لا تُعد الإرسال |
| `422` | خطأ تحقق | اعرض `errors` تحت كل حقل |
| `429` | محاولات كثيرة | اعرض «حاول بعد قليل» |
| `500` | خطأ سيرفر | اعرض رسالة عامة |

### صيغة رقم الهاتف

**أرسِل بأي صيغة** — السيرفر يوحّدها:
`07719998877` · `+9647719998877` · `9647719998877`

**يرجع دائماً** بصيغة `9647719998877`.

### الترقيم والصفحات

كل قائمة تقبل: `?page=1&per_page=15` — الحد الأقصى لـ`per_page` هو **100**.

---

## ٢. تصفّح بصفة زائر (بدون تسجيل)

هذي كلها **مفتوحة بلا توكن**. الزائر يتصفّح التطبيق كاملاً ولا يحتاج حساب إلا عند إنشاء طلب.

### `GET /customer/sliders`
السلايدرات مرتّبة حسب رقم الترتيب الذي حدّده الأدمن.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Sliders retrieved successfully",
  "data": [
    {
      "id": 1,
      "image": "http://127.0.0.1:8000/storage/sliders/kUUGNzJNagGwm1IzfXhiEbUBWOTZZUkpuKzb66RZ.png",
      "link": null
    }
  ]
}
```

### `GET /customer/categories`
الأقسام المفعّلة مع عدد خدمات كل قسم.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Categories retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "تكييف وتبريد",
      "image": "http://127.0.0.1:8000/storage/categories/jYKFxRUglrcbXyCVZ3XUZLzeuHotjST5IaH7K6iM.jpg",
      "services_count": 1
    },
    {
      "id": 2,
      "name": "سباكة",
      "image": null,
      "services_count": 2
    },
    {
      "id": 3,
      "name": "كهرباء",
      "image": null,
      "services_count": 2
    },
    {
      "id": 4,
      "name": "نجارة",
      "image": null,
      "services_count": 2
    },
    {
      "id": 5,
      "name": "تنظيف",
      "image": null,
      "services_count": 2
    }
  ]
}
```

### `GET /customer/categories/{id}/services`
خدمات قسم معيّن، مرتّبة حسب رقم الترتيب.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Services retrieved successfully",
  "data": [
    {
      "id": 5,
      "category_id": 3,
      "name": "صيانة كهرباء",
      "image": null,
      "images": [],
      "description": null
    },
    {
      "id": 6,
      "category_id": 3,
      "name": "تركيب كهرباء",
      "image": null,
      "images": [],
      "description": null
    }
  ]
}
```

### `GET /customer/services`
كل الخدمات المفعّلة (صفحة نتائج كاملة). يقبل:
- `?category_id=3` — تصفية بقسم.
- `?q=صيانه` — بحث عربي ذكي على الاسم والوصف واسم القسم (يتجاهل الهمزة والتشكيل وة/ه). للاقتراحات الفورية أثناء الكتابة استعمل `suggest` أدناه بدل هذي.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Services retrieved successfully",
  "data": [
    {
      "id": 1,
      "category_id": 1,
      "category": {
        "id": 1,
        "name": "تكييف وتبريد",
        "image": "http://127.0.0.1:8000/storage/categories/jYKFxRUglrcbXyCVZ3XUZLzeuHotjST5IaH7K6iM.jpg"
      },
      "name": "صيانة تكييف وتبريد",
      "image": "https://servix.diyala.org/storage/services/8lmYlS3bp0f2ghc9nzbyydTD4VGGSzFqzqgE864y.jpg",
      "images": [
        { "id": 1, "url": "https://servix.diyala.org/storage/services/8lmYlS3bp0f2ghc9nzbyydTD4VGGSzFqzqgE864y.jpg" },
        { "id": 2, "url": "https://servix.diyala.org/storage/services/Qm3vN8kLp2RtYx7Wc4Zb9Hd1Fj6Gs5Aa0Ee8Ii2.jpg" }
      ],
      "description": "تهعلاثقلثقل"
    },
    {
      "id": 9,
      "category_id": 5,
      "category": {
        "id": 5,
        "name": "تنظيف",
  … 
```

**صور الخدمة (معرض):**
| الحقل | الوصف |
|---|---|
| `image` | **الغلاف** — أول صورة بالمعرض، أو `null` لو الخدمة بلا صور. استعمله بالقوائم والبطاقات. |
| `images` | **المعرض كاملاً** (٠–٤ صور) مرتّباً؛ كل عنصر `{ id, url }`. استعمله بشاشة التفاصيل كسلايدر/معرض. الأول فيه = `image`. |

> قد تكون `images` فارغة `[]` و`image` = `null` — اعرض بديلاً (placeholder) حينها.

### `GET /customer/services/suggest` — بحث/اقتراحات فورية
النقطة المخصّصة لصندوق البحث بالتطبيق: الزبون يكتب أحرفاً فتطلع له خدمات مقترحة خفيفة ومرتّبة بالأهمية. **عامّة (بلا توكن).**

**المُعطيات (query):**
| المُعطى | إلزامي | الوصف |
|---|---|---|
| `q` | نعم | نص البحث. فارغ/مسافات فقط → قائمة فارغة (لا يرجّع كل الكتالوج). |
| `limit` | لا | عدد الاقتراحات، الافتراضي **8**، الأقصى **15**. |

**سلوك المطابقة:**
- **عربي ذكي:** يتجاهل شكل الهمزة (أ/إ/آ→ا)، والتشكيل، والتطويل، وة→ه، وى→ي. فـ«صيانه» تطابق «صيانة».
- يطابق **اسم الخدمة** و**اسم قسمها** (كتابة «سباكة» تُظهر خدمات قسم السباكة).
- **الترتيب:** ما يبدأ بالكلمة أولاً، ثم ترتيب العرض، ثم الاسم.
- يُظهر الخدمات المفعّلة فقط (ويخفي خدمات الأقسام المخفيّة).

**مثال:** `GET /customer/services/suggest?q=صيانه&limit=5`

**الرد `200`:**
```json
{
  "success": true,
  "message": "Suggestions retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "صيانة تكييف وتبريد",
      "image": "https://servix.diyala.org/storage/services/8lmYlS3bp0f2ghc9nzbyydTD4VGGSzFqzqgE864y.jpg",
      "category": { "id": 1, "name": "تكييف وتبريد" }
    },
    {
      "id": 3,
      "name": "صيانة سباكة",
      "image": null,
      "category": { "id": 2, "name": "سباكة" }
    }
  ]
}
```

**حقول العنصر:**
| الحقل | الوصف |
|---|---|
| `id` | معرّف الخدمة — استعمله لفتح الشاشة عبر `GET /customer/services/{id}`. |
| `name` | اسم الخدمة (للعرض بالاقتراح). |
| `image` | مصغّرة الخدمة أو `null`. |
| `category.id` · `category.name` | القسم الذي تتبعه الخدمة (لعرضه ككونتكست وللتنقّل). |

**استعلام فارغ → `200` بقائمة فارغة:**
```json
{ "success": true, "message": "Suggestions retrieved successfully", "data": [] }
```

**نصائح للتطبيق:**
- طبّق **debounce ~300ms** على الكتابة قبل الطلب (لا ترسل مع كل حرف).
- عند اختيار اقتراح، انتقل لتفاصيل الخدمة بـ`id` عبر `GET /customer/services/{id}`.
- لعرض «كل النتائج» بصفحة كاملة (مع صفحات ووصف) استعمل `GET /customer/services?q=...`.

### `GET /customer/services/{id}`
تفاصيل خدمة واحدة — نفس شكل العنصر أعلاه (مع `images` كاملة للمعرض).

### `GET /customer/governorates`
المحافظات المفعّلة.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Governorates retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "بغداد",
      "districts_count": 3
    },
    {
      "id": 2,
      "name": "البصرة",
      "districts_count": 3
    },
    {
      "id": 3,
      "name": "نينوى",
      "districts_count": 3
    },
    {
      "id": 4,
      "name": "أربيل",
      "districts_count": 3
    },
    {
      "id": 5,
      "name": "النجف",
      "districts_count": 3
    },
    {
      "id": 6,
      "name": "كربلاء",
      "districts_count": 3
    },
    {
      "id": 7,
      "name": "ذي قار",
  … 
```

### `GET /customer/governorates/{id}/districts`
أقضية محافظة معيّنة **فقط**.

> ⚠️ لا تعرض كل الأقضية بقائمة واحدة. المستخدم يختار المحافظة أولاً، ثم تُحمَّل أقضيتها. والسيرفر يرفض أي قضاء لا يتبع المحافظة المختارة.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Districts retrieved successfully",
  "data": [
    {
      "id": 13,
      "governorate_id": 1,
      "name": "الشرقي بغداد"
    },
    {
      "id": 14,
      "governorate_id": 1,
      "name": "الغربي بغداد"
    },
    {
      "id": 1,
      "governorate_id": 1,
      "name": "مركز بغداد"
    }
  ]
}
```

### `GET /customer/specializations`
الاختصاصات — تحتاجها في **استمارة انضمام الفنيين**.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Specializations retrieved successfully",
  "data": [
    {
      "id": 2,
      "name": "سبّاك"
    },
    {
      "id": 5,
      "name": "عامل تنظيف"
    },
    {
      "id": 3,
      "name": "فني تبريد"
    },
    {
      "id": 1,
      "name": "كهربائي"
    },
    {
      "id": 4,
      "name": "نجّار"
    }
  ]
}
```

### `GET /customer/blog` · `GET /customer/blog/{id}`
المقالات المنشورة، الأحدث أولاً. مقسّمة على صفحات.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Blog posts retrieved successfully",
  "data": [
    {
      "id": 1,
      "title": "joweijfwef",
      "image": "http://127.0.0.1:8000/storage/blog/VqH1kef1bjiz9bDpI9ZLO4Ub0GgCIrSZjKuE0slt.jpg",
      "content": "klwenfoiwenfe\r\nWEFwekfmoweinfwe",
      "published_at": "2026-08-13T00:00:00+03:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 1,
    "last_page": 1
  }
}
```

### `GET /customer/legal-pages/{key}`
المفاتيح المتاحة: **`privacy_policy`** و **`terms_of_use`** فقط.
المحتوى **HTML** — اعرضه بمكوّن يدعم HTML.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Legal page retrieved successfully",
  "data": {
    "key": "privacy_policy",
    "title": "سياسة الخصوصية",
    "content": "<p></p>",
    "updated_at": "2026-08-13T20:19:14+03:00"
  }
}
```

---

## ٣. التسجيل والدخول

### دورة التسجيل

```
POST /auth/register    →  201، البيانات بالكاش والرمز انطلق  →  شاشة رمز التحقق
POST /auth/verify-otp  →  201، يُنشأ الحساب active + توكن     →  الرئيسية مباشرة
```

**خطوتان.** الحساب **لا يُنشأ إلا بعد نجاح الرمز**؛ التسجيل يحفظ البيانات مؤقتاً (١٠ دقائق) ويرسل الرمز، والتوثيق يُنشئ الحساب ويرجّع توكناً فيدخل المستخدم مباشرة. لا حالة معلّقة، لا توثيق كحالة. (التفاصيل الكاملة في `docs/customer-auth-api.md`.)

### `POST /customer/auth/register`

**الطلب:**
```json
{
  "name": "زائر تجريبي",
  "gender": "male",
  "phone": "07719998877",
  "password": "hoame-2026",
  "password_confirmation": "hoame-2026",
  "governorate_id": 1,
  "district_id": 1,
  "terms_accepted": true
}
```

| الحقل | القيد |
|---|---|
| `name` | مطلوب · ٣–٢٥٥ حرف |
| `gender` | مطلوب · `male` أو `female` |
| `phone` | مطلوب · رقم عراقي |
| `password` | مطلوب · ٨–٦٤ خانة · مع `password_confirmation` |
| `governorate_id` | مطلوب · من `GET /customer/governorates` |
| `district_id` | مطلوب · من `GET /customer/governorates/{governorate_id}/districts` — **يتبع المحافظة المختارة** |
| `terms_accepted` | مطلوب · `true` |

**الرد `201`:**
```json
{
  "success": true,
  "message": "أرسلنا رمز التحقق إلى واتساب",
  "data": {
    "phone": "9647719998877",
    "resend_in": 59
  }
}
```

> **لا يوجد توكن، ولا حساب بعد** — البيانات بالكاش ١٠ دقائق. انتقل لشاشة الرمز وابدأ عدّاداً بـ`resend_in`.

**رقم يخصّ حساباً موجوداً `422`:**
```json
{
  "success": false,
  "message": "البيانات المدخلة غير صحيحة",
  "errors": {
    "phone": [
      "رقم الهاتف مسجّل بالفعل، سجّل الدخول أو استعد كلمة السر"
    ]
  }
}
```

### `POST /customer/auth/verify-otp`

**الطلب:** `{ "phone": "07719998877", "code": "123456" }`

**الرد `201` — يُنشأ الحساب `active` ويرجّع توكناً (يدخل التطبيق مباشرة):**
```json
{
  "success": true,
  "message": "تم إنشاء حسابك بنجاح",
  "data": {
    "user": { "id": 40, "name": "زائر تجريبي", "phone": "9647719998877", "status": "active", "status_label": "نشط", "…": "بقية الحقول" },
    "token": "210|xxxxxxxxxxxx",
    "token_type": "Bearer"
  }
}
```

**رمز خاطئ `422`:** `errors.otp[0]` = `INVALID_CODE`.
**انتهت الجلسة `422`:** `errors.phone[0]` = `"انتهت جلسة التسجيل، أعد إدخال بياناتك"`.

### `POST /customer/auth/resend-otp`

زر «لم يصلني الرمز» في **شاشتَي الرمز**. تسجيل قيد التنفيذ بالكاش ← رمز تسجيل؛ وإلا ← رمز استعادة لحساب موجود.

**الطلب:** `{ "phone": "07719998877" }`

**الرد `200`:**
```json
{
  "success": true,
  "message": "تم إرسال رمز جديد",
  "data": {
    "phone": "9647719998877",
    "purpose": "register",
    "resend_in": 59
  }
}
```

| `data.purpose` | متى | ماذا يفتحه الرمز |
|---|---|---|
| `register` | تسجيل قيد التنفيذ بالكاش | `verify-otp` |
| `reset` | حساب موجود | `reset-password` |

**رقم بلا حساب `422`:**
```json
{
  "success": false,
  "message": "البيانات المدخلة غير صحيحة",
  "errors": {
    "phone": [
      "لا يوجد حساب بهذا الرقم"
    ]
  }
}
```

**قبل انتهاء المهلة `422`:**
```json
{
  "success": false,
  "message": "انتظر 59 ثانية قبل إعادة إرسال الرمز",
  "errors": {
    "otp": [
      "COOLDOWN"
    ]
  }
}
```

| `errors.otp[0]` | المعنى |
|---|---|
| `INVALID_CODE` | الرمز غير صحيح |
| `EXPIRED` | انتهت صلاحية الرمز — اطلب رمزاً جديداً |
| `COOLDOWN` | لم تمرّ ٦٠ ثانية على آخر إرسال |
| `NOT_CONFIGURED` | خدمة الرسائل غير مهيّأة عند الإدارة |

### `POST /customer/auth/login`

**الطلب:**
```json
{ "phone": "07719998877", "password": "hoame-2026" }
```

**الرد `200`:**
```json
{
  "success": true,
  "message": "تم تسجيل الدخول بنجاح",
  "data": {
    "user": {
      "id": 5,
      "name": "زائر تجريبي",
      "gender": "male",
      "phone": "9647719998877",
      "status": "active",
      "status_label": "نشط",
      "governorate_id": 1,
      "governorate": {
        "id": 1,
        "name": "بغداد"
      },
      "district_id": 1,
      "district": {
        "id": 1,
        "name": "مركز بغداد"
      },
      "created_at": "2026-08-14T15:25:41+03:00"
    },
    "token": "87|tIqVV7j0QSQD6MrgSLeHGXdT0sRAQgRQAekPrd7sb2f97e02",
    "token_type": "Bearer"
  }
}
```

**كلمة سر خاطئة `422`:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "phone": [
      "رقم الهاتف أو كلمة السر غير صحيحة"
    ]
  }
}
```

> حساب موقوف أو مجدول للحذف يُرفض دخوله برسالة مناسبة — انظر جدول حالات الحساب.

### `GET /customer/auth/me` 🔒
يرجّع بيانات صاحب التوكن. استعملها عند فتح التطبيق للتأكد أن التوكن ما زال صالحاً.

**الرد `200`:**
```json
{
  "success": true,
  "message": "User retrieved successfully",
  "data": {
    "id": 5,
    "name": "زائر تجريبي",
    "gender": "male",
    "phone": "9647719998877",
    "status": "active",
    "status_label": "نشط",
    "governorate_id": 1,
    "governorate": {
      "id": 1,
      "name": "بغداد"
    },
    "district_id": 1,
    "district": {
      "id": 1,
      "name": "مركز بغداد"
    },
    "orders_count": 0,
    "created_at": "2026-08-14T15:25:41+03:00"
  }
}
```

### `POST /customer/auth/logout` 🔒
يلغي التوكن الحالي فقط.

```json
{ "success": true, "message": "تم تسجيل الخروج بنجاح", "data": null }
```

---

## ٤. نسيت كلمة السر

```
١) POST /auth/forgot-password  → يُرسل رمز على واتساب
٢) POST /auth/reset-password   → رمز + كلمة سر جديدة ← بلا توكن
٣) POST /auth/login            → بكلمة السر الجديدة  ← المستخدم داخل
```

> إعادة التعيين **لا ترجع توكن**، وتُلغي كل جلسات الحساب. بعد `200` انقل المستخدم لشاشة الدخول.

### `POST /customer/auth/forgot-password`

**الطلب:** `{ "phone": "07719998877" }`

**الرد `200`:**
```json
{
  "success": true,
  "message": "أرسلنا رمز استعادة كلمة السر إلى واتساب",
  "data": {
    "phone": "9647719998877",
    "resend_in": 59
  }
}
```

> رقم بلا حساب: `422` مع `"لا يوجد حساب بهذا الرقم"`. وحساب موقوف/مجدول للحذف يُرفض إرسال رمز له.

### `POST /customer/auth/reset-password`

**الطلب:**
```json
{
  "phone": "07719998877",
  "code": "123456",
  "password": "hoame-2027",
  "password_confirmation": "hoame-2027"
}
```

**الرد `200`:**
```json
{
  "success": true,
  "message": "تم تغيير كلمة السر، سجّل الدخول بكلمتك الجديدة",
  "data": null
}
```

**رمز خاطئ `422`:**
```json
{
  "success": false,
  "message": "الرمز غير صحيح",
  "errors": {
    "otp": [
      "INVALID_CODE"
    ]
  }
}
```

> **كل توكنات الحساب تُلغى** عند تغيير كلمة السر — على كل الأجهزة، بما فيها الجهاز الحالي.

---

## ٥. الملف الشخصي 🔒

### `GET /customer/profile`

**الرد `200`:**
```json
{
  "success": true,
  "message": "Profile retrieved successfully",
  "data": {
    "id": 5,
    "name": "زائر تجريبي",
    "gender": "male",
    "phone": "9647719998877",
    "status": "active",
    "status_label": "نشط",
    "governorate_id": 1,
    "governorate": {
      "id": 1,
      "name": "بغداد"
    },
    "district_id": 1,
    "district": {
      "id": 1,
      "name": "مركز بغداد"
    },
    "orders_count": 1,
    "created_at": "2026-08-14T15:25:41+03:00"
  }
}
```

### `PUT /customer/profile`

**الطلب:**
```json
{ "name": "زائر تجريبي معدّل", "gender": "male", "governorate_id": 1, "district_id": 1 }
```

> **رقم الهاتف لا يُعدَّل** — هو معرّف الحساب.

**الرد `200`:**
```json
{
  "success": true,
  "message": "تم تحديث بياناتك بنجاح",
  "data": {
    "id": 5,
    "name": "زائر تجريبي معدّل",
    "gender": "male",
    "phone": "9647719998877",
    "status": "active",
    "status_label": "نشط",
    "governorate_id": 1,
    "governorate": {
      "id": 1,
      "name": "بغداد"
    },
    "district_id": 1,
    "district": {
      "id": 1,
      "name": "مركز بغداد"
    },
    "orders_count": 1,
    "created_at": "2026-08-14T15:25:41+03:00"
  }
}
```

### `POST /customer/profile/change-password`

**الطلب:**
```json
{
  "current_password": "hoame-2026",
  "password": "hoame-2027",
  "password_confirmation": "hoame-2027"
}
```

الجديدة لازم تختلف عن الحالية · ٨ خانات فأكثر. وكل التوكنات الأخرى تُلغى.

---

## ٦. الطلبات 🔒

### `GET /customer/visit-window`
**استدعها قبل شاشة إنشاء الطلب** — منها تبني منتقي الوقت بدل ما تكتب القواعد بالتطبيق.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Visit window retrieved successfully",
  "data": {
    "date": "2026-08-14",
    "is_open": true,
    "earliest_from": "15:27",
    "latest_from": "23:59",
    "may_end_next_day": true,
    "max_images": 4
  }
}
```

| الحقل | المعنى |
|---|---|
| `date` | يوم الزيارة — **دائماً اليوم**، والزبون يختار الوقت فقط |
| `is_open` | هل الاستقبال مفتوح الآن |
| `earliest_from` | أبكر وقت بداية مسموح |
| `latest_from` | آخر وقت بداية مسموح |
| `may_end_next_day` | **`true`** — وقت النهاية يجوز يكون بعد منتصف الليل (`23:30 → 00:30`) |
| `max_images` | الحد الأقصى للصور |

> **لا حد أدنى لمدة الزيارة.** الشرط الوحيد: النهاية ≠ البداية.

### `POST /customer/orders`
**`multipart/form-data`** لأن فيه صوراً.

| الحقل | إلزامي | القيد |
|---|---|---|
| `service_id` | ✅ | خدمة مفعّلة |
| `description` | ✅ | ٥–٢٠٠٠ حرف |
| `time_from` | ✅ | `H:i` مثل `16:00` |
| `time_to` | ✅ | `H:i` · يختلف عن البداية |
| `latitude` / `longitude` | ✅ | من خريطة التطبيق |
| `landmark` | ❌ | أقرب نقطة دالة · ٢٥٥ حرف |
| `images[]` | ❌ | حتى ٤ صور · jpg/png/webp · ٤ ميغا للصورة |

> **لا تُرسل** المحافظة ولا القضاء ولا التاريخ — السيرفر ياخذها من ملف الزبون، والتاريخ دائماً اليوم.

**الرد `201`:**
```json
{
  "success": true,
  "message": "تم استلام طلبك برقم HS-260814-0006، شكراً لك",
  "data": {
    "id": 33,
    "order_number": "HS-260814-0006",
    "status": "pending",
    "status_label": "معلّق",
    "is_final": false,
    "can_cancel": true,
    "description": "المكيّف ما يبرّد ويطلع صوت من الوحدة الخارجية",
    "scheduled_date": "2026-08-14",
    "time_from": "16:00",
    "time_to": "18:00",
    "visit_ends_next_day": false,
    "latitude": 33.312805,
    "longitude": 44.361488,
    "landmark": "قرب جامع أم الطبول",
    "inspection_note": null,
    "cancelled_at": null,
    "service": {
      "id": 5,
      "category_id": 3,
      "category": {
        "id": 3,
        "name": "كهرباء",
        "image": null
      },
      "name": "صيانة كهرباء",
      "image": null,
      "description": null
    },
    "governorate": {
      "id": 1,
      "name": "بغداد"
    },
    "district": {
      "id": 1,
      "governorate_id": 1,
      "name": "مركز بغداد"
    },
    "images": [
      {
        "id": 3,
        "url": "http://127.0.0.1:8000/storage/orders/33/y4joQRDTEq4c2u5c7OqivOI9719O87XNNyj40kuK.png"
      },
      {
        "id": 4,
        "url": "http://127.0.0.1:8000/storage/orders/33/u4PU6fT05gYGKfqLRLeBLgRVrQoySbA2mBdIUJdR.png"
      }
    ],
    "timeline": [
      {
        "status": "pending",
        "status_label": "معلّق",
        "note": "تم استلام الطلب",
        "at": "2026-08-14T15:26:08+03:00"
      }
    ],
    "created_at": "2026-08-14T15:26:08+03:00"
  }
}
```

**أمثلة الرفض (كلها `422`):**

| الحالة | الرسالة |
|---|---|
| `16:00 → 16:00` | وقت النهاية يجب أن يختلف عن وقت البداية |
| وقت مضى | لا يمكن اختيار وقت مضى |
| ٥ صور | الحد الأقصى 4 صور |
| وصف قصير | اكتب وصفاً أوضح للمشكلة |
| ملف الزبون ناقص | أكمل بيانات محافظتك وقضائك من الملف الشخصي قبل الطلب |

### `GET /customer/orders`
طلبات صاحب التوكن فقط. يقبل `?status=pending` و`?page=`.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Orders retrieved successfully",
  "data": [
    {
      "id": 33,
      "order_number": "HS-260814-0006",
      "status": "pending",
      "status_label": "معلّق",
      "is_final": false,
      "can_cancel": true,
      "description": "المكيّف ما يبرّد ويطلع صوت من الوحدة الخارجية",
      "scheduled_date": "2026-08-14",
      "time_from": "16:00",
      "time_to": "18:00",
      "visit_ends_next_day": false,
      "latitude": 33.312805,
      "longitude": 44.361488,
      "landmark": "قرب جامع أم الطبول",
      "inspection_note": null,
      "cancelled_at": null,
      "service": {
        "id": 5,
        "category_id": 3,
        "category": {
          "id": 3,
          "name": "كهرباء",
          "image": null
        },
        "name": "صيانة كهرباء",
        "image": null,
        "description": null
      },
      "governorate": {
        "id": 1,
        "name": "بغداد"
      },
      "district": {
        "id": 1,
        "governorate_id": 1,
        "name": "مركز بغداد"
      },
      "created_at": "2026-08-14T15:26:08+03:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
  }
}
```

### `GET /customer/orders/{id}`
تفاصيل الطلب مع **الفني** و**سجل الأحداث**.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Order retrieved successfully",
  "data": {
    "id": 33,
    "order_number": "HS-260814-0006",
    "status": "pending",
    "status_label": "معلّق",
    "is_final": false,
    "can_cancel": true,
    "description": "المكيّف ما يبرّد ويطلع صوت من الوحدة الخارجية",
    "scheduled_date": "2026-08-14",
    "time_from": "16:00",
    "time_to": "18:00",
    "visit_ends_next_day": false,
    "latitude": 33.312805,
    "longitude": 44.361488,
    "landmark": "قرب جامع أم الطبول",
    "inspection_note": null,
    "cancelled_at": null,
    "service": {
      "id": 5,
      "category_id": 3,
      "category": {
        "id": 3,
        "name": "كهرباء",
        "image": null
      },
      "name": "صيانة كهرباء",
      "image": null,
      "description": null
    },
    "governorate": {
      "id": 1,
      "name": "بغداد"
    },
    "district": {
      "id": 1,
      "governorate_id": 1,
      "name": "مركز بغداد"
    },
    "images": [
      {
        "id": 3,
        "url": "http://127.0.0.1:8000/storage/orders/33/y4joQRDTEq4c2u5c7OqivOI9719O87XNNyj40kuK.png"
      },
      {
        "id": 4,
        "url": "http://127.0.0.1:8000/storage/orders/33/u4PU6fT05gYGKfqLRLeBLgRVrQoySbA2mBdIUJdR.png"
      }
    ],
    "timeline": [
      {
        "status": "pending",
        "status_label": "معلّق",
        "note": "تم استلام الطلب",
        "at": "2026-08-14T15:26:08+03:00"
      }
    ],
    "created_at": "2026-08-14T15:26:08+03:00"
  }
}
```

**حقول مهمة:**

| الحقل | الاستعمال |
|---|---|
| `can_cancel` | **اعرض زر الإلغاء فقط لو `true`** |
| `is_final` | الطلب انتهى (منجَز أو ملغى) |
| `visit_ends_next_day` | `true` ← اعرض «(اليوم التالي)» جنب وقت النهاية |
| `technician` | `null` قبل التعيين |
| `timeline` | سجل الأحداث للعرض كخط زمني |

**حالات الطلب:**

| القيمة | العربية |
|---|---|
| `pending` | معلّق — الزبون يقدر يلغي |
| `confirmed` | مؤكّد — يُقفل الإلغاء |
| `assigned` | تم تعيين فني |
| `inspected` | تم الكشف |
| `completed` | تم إنجاز الخدمة |
| `cancelled` | ملغى |

### `POST /customer/orders/{id}/cancel`

**الطلب:** `{ "note": "غيّرت رأيي" }` — `note` اختياري.

**الرد `200`:**
```json
{
  "success": true,
  "message": "تم إلغاء الطلب",
  "data": {
    "id": 33,
    "order_number": "HS-260814-0006",
    "status": "cancelled",
    "status_label": "ملغى",
    "is_final": true,
    "can_cancel": false,
    "description": "المكيّف ما يبرّد ويطلع صوت من الوحدة الخارجية",
    "scheduled_date": "2026-08-14",
    "time_from": "16:00",
    "time_to": "18:00",
    "visit_ends_next_day": false,
    "latitude": 33.312805,
    "longitude": 44.361488,
    "landmark": "قرب جامع أم الطبول",
    "inspection_note": null,
    "cancelled_at": "2026-08-14T15:26:08+03:00",
    "service": {
      "id": 5,
      "category_id": 3,
      "category": {
        "id": 3,
        "name": "كهرباء",
  … 
```

> بعد تأكيد الطلب: `422` مع `"لا يمكن إلغاء الطلب بعد تأكيده"`.

---

## ٧. الإشعارات 🔒

### `GET /customer/notifications`

**الرد `200`:**
```json
{
  "success": true,
  "message": "Notifications retrieved successfully",
  "data": [
    {
      "id": 36,
      "title": "تم تعيين فني لطلبك",
      "body": "الفني حيدر كاظم سيتولى طلبك رقم HS-260816-0007",
      "type": "order_status",
      "is_read": false,
      "read_at": null,
      "order_id": 41,
      "order_number": "HS-260816-0007",
      "status": "assigned",
      "blog_post_id": null,
      "data": {
        "order_id": 41,
        "order_number": "HS-260816-0007",
        "from_status": "confirmed",
        "to_status": "assigned"
      },
      "created_at": "2026-08-16T12:10:30+03:00"
    },
    {
      "id": 37,
      "title": "مقال جديد",
      "body": "خمس علامات تدل على تسريب في أنابيب المطبخ",
      "type": "blog_post",
      "is_read": false,
      "read_at": null,
      "order_id": null,
      "order_number": null,
      "status": null,
      "blog_post_id": 7,
      "data": { "blog_post_id": 7, "title": "خمس علامات تدل على تسريب في أنابيب المطبخ" },
      "created_at": "2026-08-16T12:10:30+03:00"
    }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 2, "last_page": 1 }
}
```

**الأنواع:**

| `type` | متى يصل | المفتاح الذي يفتح الشاشة |
|---|---|---|
| `order_status` | تغيّرت حالة طلبك | `order_id` |
| `technician_reassigned` | تبدّل الفني المكلّف بطلبك | `order_id` |
| `blog_post` | نُشر مقال جديد بالمدوّنة | `blog_post_id` |

استعمل `order_id` أو `blog_post_id` لفتح الشاشة الصحيحة عند الضغط. الحقل `data` يحمل الحمولة الكاملة كما وصلت في الإشعار الفوري (OneSignal)، فتقدر تعتمد معالِجاً واحداً للاثنين.

### `GET /customer/notifications/unread-count`
للنقطة الحمراء على أيقونة الجرس.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Unread count retrieved successfully",
  "data": {
    "unread_count": 0
  }
}
```

### `POST /customer/notifications/{id}/read` · `POST /customer/notifications/read-all`
تعليم إشعار واحد أو الكل كمقروء.

---

## ٨. استمارة انضمام الفنيين 🌐

**مفتوحة للزائر بلا تسجيل.**

### `GET /customer/technician-application`
**استدعها أولاً.** إذا `is_open: false` **أخفِ المدخل كاملاً** من التطبيق.

**الرد `200`:**
```json
{
  "success": true,
  "message": "Technician application form retrieved successfully",
  "data": {
    "is_open": true,
    "required_documents": [
      {
        "key": "personal",
        "label": "صورة شخصية"
      },
      {
        "key": "id_front",
        "label": "وجه البطاقة الوطنية"
      },
      {
        "key": "id_back",
        "label": "ظهر البطاقة الوطنية"
      },
      {
        "key": "residence_front",
        "label": "وجه بطاقة السكن"
      },
      {
        "key": "residence_back",
        "label": "ظهر بطاقة السكن"
      }
    ],
    "work_samples_key": "work_samples",
    "work_samples_limit": 4,
    "max_file_mb": 4
  }
}
```

### ترتيب شاشة الاستمارة

الاستمارة تحتاج ثلاث قوائم من السيرفر قبل ما يبدأ التعبئة:

```
١) GET /customer/technician-application      → is_open · الأوراق المطلوبة · الحدود
٢) GET /customer/governorates                → قائمة المحافظات
٣) GET /customer/specializations             → قائمة الاختصاصات
```

**وعند اختيار المحافظة:**

```
٤) GET /customer/governorates/{id}/districts → أقضية تلك المحافظة فقط
```

> **قاعدة إلزامية:** قائمة الأقضية **تُحمَّل بعد اختيار المحافظة**، ولا تُعرض كل أقضية العراق بقائمة واحدة. وإذا بدّل المتقدّم المحافظة، **صفّر اختيار القضاء** وأعد تحميل القائمة — وإلا يبقى قضاء المحافظة السابقة محدداً والسيرفر يرفض الإرسال.

**مثال — أقضية بغداد `GET /customer/governorates/1/districts`:**
```json
{
  "success": true,
  "message": "Districts retrieved successfully",
  "data": [
    { "id": 13, "governorate_id": 1, "name": "الشرقي بغداد" },
    { "id": 14, "governorate_id": 1, "name": "الغربي بغداد" },
    { "id": 1, "governorate_id": 1, "name": "مركز بغداد" }
  ]
}
```

**والسيرفر يتحقق أيضاً** — لو أُرسل قضاء لا يتبع المحافظة، يرجّع `422`:
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": { "district_id": ["اختر قضاءً يتبع المحافظة المختارة"] }
}
```

هذي الحماية موجودة لأن النقطة عامة — أي أحد يقدر يرسل طلباً مباشرة بلا مرور على شاشة التطبيق.

### `POST /customer/technician-application`
**`multipart/form-data`**

| الحقل | إلزامي | القيد |
|---|---|---|
| `full_name` | ✅ | **الاسم الثلاثي** — ٣ كلمات على الأقل |
| `phone` | ✅ | رقم عراقي |
| `governorate_id` | ✅ | من `GET /customer/governorates` |
| `district_id` | ✅ | من `GET /customer/governorates/{governorate_id}/districts` |
| `specialization_ids[]` | ✅ | من `GET /customer/specializations` · واحد على الأقل |
| `personal` | ✅ | صورة شخصية |
| `id_front` / `id_back` | ✅ | وجه وظهر البطاقة الوطنية |
| `residence_front` / `residence_back` | ✅ | وجه وظهر بطاقة السكن |
| `work_samples[]` | ❌ | حتى ٤ نماذج أعمال |

الصور: jpg/png/webp · ٤ ميغا للصورة · **٩ صور بالحد الأقصى = ٣٦ ميغا للطلب الواحد**.

**نجاح `201`:**
```json
{ "success": true, "message": "شكراً على إرسال طلبك، سيتم الاتصال بك", "data": null }
```

**حالات الرفض `422`:**

| الحالة | الرسالة |
|---|---|
| الرقم قدّم سابقاً | تم التسجيل مسبقاً بهذا الرقم |
| الرقم مسجّل كفني | هذا الرقم مسجّل كفني بالفعل |
| الاستمارة مطفأة | التسجيل كفني مغلق حالياً |
| اسم ثنائي | اكتب الاسم الثلاثي كاملاً |
| ورقة ناقصة | ظهر بطاقة السكن مطلوبة |

> لا يوجد إشعار للمتقدّم — **الإدارة تتصل به يدوياً.**

---

## ٩. الأخطاء والحدود

### توكن مفقود أو منتهٍ `401`
```json
{
  "success": false,
  "message": "Unauthorized",
  "errors": []
}
```
**تصرّف التطبيق:** امسح التوكن وارجع لشاشة الدخول فوراً.

### خطأ تحقق `422`
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "phone": [
      "رقم الهاتف غير صحيح"
    ],
    "password": [
      "كلمة السر مطلوبة"
    ]
  }
}
```
**تصرّف التطبيق:** اعرض كل رسالة تحت حقلها. المفتاح = اسم الحقل.

### حدود المحاولات — `429`

| العملية | الحد |
|---|---|
| إرسال رمز (إعادة إرسال · نسيت كلمة السر) | ٤ محاولات / ١٠ دقائق **لكل رقم** |
| التحقق من الرمز · إعادة التعيين | ٨ محاولات / ١٠ دقائق **لكل رقم** |
| التسجيل · تسجيل الدخول | **بلا حد** |
| استمارة الفنيين | ٣ / ساعة لكل IP **و** ٣ / ساعة لكل رقم |

عند `429` اعرض «حاول بعد قليل» ولا تعيد المحاولة تلقائياً.

---

## ١٠. إشعارات الجوال (OneSignal)

الإشعارات تُرسل عبر **OneSignal**، والاستهداف بـ`external_user_id` — **لا نخزّن player_id إطلاقاً**.

**بعد نجاح تسجيل الدخول أو التحقق، نفّذ:**
```dart
OneSignal.login("user:" + user.id.toString());   // مثال: user:5
```

**وعند تسجيل الخروج:**
```dart
OneSignal.logout();
```

بدون هذا **لن يصل أي إشعار للجهاز**.

عند الضغط على الإشعار، اقرأ `data`:

| المفتاح | الشاشة |
|---|---|
| `order_id` | تفاصيل الطلب |
| `blog_post_id` | المقال |

---

## ١١. حالة الحساب

كل ردّ يحمل بيانات المستخدم يحوي `status` و`status_label`. اقرأها عند فتح التطبيق وبعد كل دخول.

| `status` | `status_label` | متى تصير | يقدر يستعمل التطبيق؟ |
|---|---|---|---|
| `active` | نشط | بعد توثيق الرقم بالرمز | ✅ |
| `inactive` | غير نشط | أوقفته الإدارة | ❌ |
| `suspended` | موقوف | أوقفته الإدارة | ❌ |
| `scheduled_for_deletion` | مجدول للحذف | **طلب الحذف بنفسه** | ❌ |

**عند محاولة الدخول بحساب غير نشط — `422`:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": { "phone": ["حسابك غير مفعّل، راجع الإدارة"] }
}
```

**عند حساب طلب صاحبه حذفه — `422`:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": { "phone": ["حسابك مجدول للحذف"] }
}
```
اعرض الرسالة كما هي. الحساب لا يُستعمل حتى تلغي الإدارة الطلب.

> **مهم:** إيقاف الحساب من الإدارة **يسحب كل التوكنات فوراً**. فأي طلب بعدها يرجّع `401` — عالجها بمسح التوكن والرجوع لشاشة الدخول.

---

## ١٢. حذف الحساب

الزبون يطلب الحذف، **ولا يُحذف شيء فوراً** — الطلب يوصل الإدارة وهي تقرر. سبب ذلك أن حذف الحساب يمسح معه كل طلباته وسجلّها.

### مسار الشاشة

```
يضغط «حذف حسابي»
  ↓  تأكيد داخل التطبيق
POST /customer/profile/delete-request
  ↓  رسالة: تم إرسال طلب الحذف إلى الإدارة
سجّل خروجاً محلياً — امسح التوكن وارجع لشاشة الدخول
```

**السيرفر يلغي كل التوكنات بنفسه**، فلا حاجة لاستدعاء `logout` بعدها.

### `POST /customer/profile/delete-request` 🔒

بلا جسم طلب.

**الرد `200`:**
```json
{
  "success": true,
  "message": "تم إرسال طلب الحذف إلى الإدارة",
  "data": null
}
```

**اعرض للمستخدم نص `message` كما هو، ثم امسح التوكن محلياً.**

**التوكن القديم بعدها `401` — يثبت أن الجلسة انتهت فعلاً:**
```json
{
  "success": false,
  "message": "Unauthorized",
  "errors": []
}
```

### إذا أرسل الطلب مرتين — `422`
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "account": [
      "طلب حذف الحساب مُرسل مسبقاً وقيد المراجعة"
    ]
  }
}
```

### حالة «مجدول للحذف»

بعد الطلب **يُقفل الحساب**: الجلسة تنتهي، وأي محاولة دخول ترجّع `422` بـ«حسابك مجدول للحذف».

بياناته وطلباته **تبقى كما هي** — لا يُحذف شيء. والإدارة تقدر تلغي الطلب فيرجع الحساب نشطاً ويدخل عادي.

وبياناته ترجع بحقلين إضافيين:

| الحقل | المعنى |
|---|---|
| `deletion_requested` | `true` = في طلب حذف قيد المراجعة |
| `deletion_requested_at` | وقت إرسال الطلب |

**مثال — الملف الشخصي بعد الطلب:**
```json
{
  "success": true,
  "message": "Profile retrieved successfully",
  "data": {
    "id": 8,
    "name": "زبون حذف الحساب",
    "gender": "male",
    "phone": "9647788800099",
    "status": "active",
    "status_label": "نشط",
    "governorate_id": 1,
    "governorate": {
      "id": 1,
      "name": "بغداد"
    },
    "district_id": 1,
    "district": {
      "id": 1,
      "name": "مركز بغداد"
    },
    "orders_count": 0,
    "deletion_requested": true,
    "deletion_requested_at": "2026-08-14T15:42:05+03:00",
    "created_at": "2026-08-14T15:42:05+03:00"
  }
}
```

> **تصرّف التطبيق:** بعد نجاح الطلب اعرض `message` ثم **امسح التوكن وارجع لشاشة الدخول**. لن يستطيع الدخول مرة أخرى، فلا حاجة لشاشة «قيد المراجعة».

**الحالات مجتمعة:**

| الوضع | `status` | `deletion_requested` | ما يشوفه المستخدم |
|---|---|---|---|
| حساب عادي | `active` | `false` | كل شي طبيعي |
| طلب الحذف | `scheduled_for_deletion` | `true` | «حسابك مجدول للحذف» عند الدخول |
| أوقفته الإدارة | `inactive` / `suspended` | `false` | «حسابك غير مفعّل، راجع الإدارة» |
| ألغت الإدارة الطلب | `active` | `false` | يرجع يدخل عادي |

---

## ١٣. ملخص المسارات

🌐 = مفتوح للزائر · 🔒 = يحتاج توكن

| | المسار | الوظيفة |
|---|---|---|
| 🌐 | `GET /customer/sliders` | السلايدرات |
| 🌐 | `GET /customer/categories` | الأقسام |
| 🌐 | `GET /customer/categories/{id}/services` | خدمات قسم |
| 🌐 | `GET /customer/services` · `/{id}` | الخدمات |
| 🌐 | `GET /customer/governorates` | المحافظات |
| 🌐 | `GET /customer/governorates/{id}/districts` | أقضية محافظة |
| 🌐 | `GET /customer/specializations` | الاختصاصات |
| 🌐 | `GET /customer/blog` · `/{id}` | المدوّنة |
| 🌐 | `GET /customer/legal-pages/{key}` | الخصوصية والشروط |
| 🌐 | `POST /customer/auth/register` | تسجيل |
| 🌐 | `POST /customer/auth/verify-otp` | إنشاء الحساب ← توكن (يدخل مباشرة) |
| 🌐 | `POST /customer/auth/resend-otp` | إعادة إرسال الرمز |
| 🌐 | `POST /customer/auth/login` | دخول |
| 🌐 | `POST /customer/auth/forgot-password` | نسيت كلمة السر |
| 🌐 | `POST /customer/auth/reset-password` | تعيين كلمة سر جديدة |
| 🌐 | `GET /customer/technician-application` | حالة استمارة الفنيين |
| 🌐 | `POST /customer/technician-application` | إرسال الاستمارة |
| 🔒 | `GET /customer/auth/me` | بياناتي |
| 🔒 | `POST /customer/auth/logout` | خروج |
| 🔒 | `GET /customer/profile` · `PUT` | الملف الشخصي |
| 🔒 | `POST /customer/profile/change-password` | تغيير كلمة السر |
| 🔒 | `POST /customer/profile/delete-request` | طلب حذف الحساب |
| 🔒 | `GET /customer/visit-window` | نافذة الزيارة المتاحة |
| 🔒 | `GET /customer/orders` · `POST` | طلباتي · إنشاء طلب |
| 🔒 | `GET /customer/orders/{id}` | تفاصيل الطلب |
| 🔒 | `POST /customer/orders/{id}/cancel` | إلغاء الطلب |
| 🔒 | `GET /customer/notifications` | الإشعارات |
| 🔒 | `GET /customer/notifications/unread-count` | عدد غير المقروء |
| 🔒 | `POST /customer/notifications/{id}/read` · `read-all` | تعليم كمقروء |

**المجموع: ٣٤ مساراً** — ١٧ مفتوح للزائر · ١٧ يحتاج توكن.
