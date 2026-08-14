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
      "description": null
    },
    {
      "id": 6,
      "category_id": 3,
      "name": "تركيب كهرباء",
      "image": null,
      "description": null
    }
  ]
}
```

### `GET /customer/services`
كل الخدمات المفعّلة. يقبل `?category_id=3` للتصفية.

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
      "image": "http://127.0.0.1:8000/storage/services/8lmYlS3bp0f2ghc9nzbyydTD4VGGSzFqzqgE864y.jpg",
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

### `GET /customer/services/{id}`
تفاصيل خدمة واحدة — نفس شكل العنصر أعلاه.

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

### دورة التسجيل كاملة

```
١) POST /auth/register     → يُنشأ حساب معلّق ويُرسل رمز على واتساب
٢) POST /auth/verify-otp   → يُوثّق الرقم ويرجع التوكن ← المستخدم داخل
     └ إن لم يصل الرمز: POST /auth/resend-otp (بعد resend_in ثانية)
```

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
  "message": "أرسلنا رمز التحقق إلى واتساب، أدخله لإكمال التسجيل",
  "data": {
    "phone": "9647719998877",
    "resend_in": 59,
    "user_id": 5
  }
}
```

> `resend_in` = عدد الثواني قبل ما يُسمح بطلب رمز جديد. شغّل عدّاداً تنازلياً بالشاشة.

### `POST /customer/auth/verify-otp`

**الطلب:**
```json
{ "phone": "07719998877", "code": "123456" }
```

**الرد `200` — هنا يستلم التطبيق التوكن:**
```json
{
  "success": true,
  "message": "تم توثيق رقمك بنجاح",
  "data": {
    "user": {
      "id": 5,
      "name": "زائر تجريبي",
      "gender": "male",
      "phone": "9647719998877",
      "phone_verified": true,
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
    "token": "86|2wDGjL7lnAvMJUlzqDLyvjxumaZKc9zqJM3MIdcZ64cdafa5",
    "token_type": "Bearer"
  }
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

| `errors.otp[0]` | المعنى |
|---|---|
| `INVALID_CODE` | الرمز غير صحيح |
| `EXPIRED` | انتهت صلاحية الرمز — اطلب رمزاً جديداً |
| `NOT_CONFIGURED` | خدمة الرسائل غير مهيّأة عند الإدارة |

### `POST /customer/auth/resend-otp`

**الطلب:** `{ "phone": "07719998877" }`

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
      "phone_verified": true,
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

> إذا كان الحساب غير موثّق، الرد يكون: `"لم يتم توثيق رقمك بعد، اطلب رمز التحقق"` — وجّه المستخدم لشاشة التحقق.

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
    "phone_verified": true,
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
٢) POST /auth/reset-password   → رمز + كلمة سر جديدة ← يرجع توكن جديد
```

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

> إن لم يكن الرقم مسجّلاً وموثّقاً: `422` مع `"لا يوجد حساب موثّق بهذا الرقم"`.

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

**الرد `200` — يرجع توكناً جديداً، فالمستخدم يدخل مباشرة:**
```json
{
  "success": true,
  "message": "تم تغيير كلمة السر بنجاح",
  "data": {
    "user": {
      "id": 5,
      "name": "زائر تجريبي",
      "gender": "male",
      "phone": "9647719998877",
      "phone_verified": true,
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
    "token": "88|mK1WexlklYgY5GtAVxtGTRZ7UkNB79OH2mHjGz7N262d242e",
    "token_type": "Bearer"
  }
}
```

> **كل توكنات الجهاز الأخرى تُلغى** عند تغيير كلمة السر.

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
    "phone_verified": true,
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
    "phone_verified": true,
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
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 0,
    "last_page": 1
  }
}
```

**الأنواع:**

| `type` | متى يصل |
|---|---|
| `order_status` | تغيّرت حالة طلبك |
| `technician_reassigned` | تبدّل الفني المكلّف بطلبك |
| `blog_post` | نُشر مقال جديد بالمدوّنة |

`data` تحمل `order_id` أو `blog_post_id` — استعملها لفتح الشاشة الصحيحة عند الضغط.

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
| إرسال رمز (تسجيل · إعادة إرسال · نسيت كلمة السر) | ٤ محاولات / ١٠ دقائق **لكل رقم** |
| التحقق من الرمز · إعادة التعيين | ٨ محاولات / ١٠ دقائق **لكل رقم** |
| تسجيل الدخول | ٢٠ محاولة / دقيقة **لكل رقم** |
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
| `pending` | قيد الانتظار | لحظة التسجيل — قبل توثيق الرقم | ❌ |
| `active` | نشط | بعد إدخال رمز الواتساب بنجاح | ✅ |
| `inactive` | غير نشط | أوقفته الإدارة | ❌ |
| `suspended` | موقوف | أوقفته الإدارة | ❌ |

**عند محاولة الدخول بحساب غير نشط — `422`:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": { "phone": ["حسابك غير مفعّل، راجع الإدارة"] }
}
```

**عند حساب غير موثّق — `422`:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": { "phone": ["لم يتم توثيق رقمك بعد، اطلب رمز التحقق"] }
}
```
وجّهه لشاشة إدخال الرمز واستدعِ `POST /auth/resend-otp`.

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

### حالة «طلب حذف مُرسل»

بعد الطلب، **يقدر الزبون يسجّل دخولاً من جديد** ويستعمل التطبيق عادي — الطلب مجرد إشارة للإدارة، مو إيقافاً.

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
    "phone_verified": true,
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

> **تصرّف التطبيق:** إذا `deletion_requested = true` اعرض شريطاً بشاشة الحساب:
> «طلب حذف حسابك قيد المراجعة لدى الإدارة» — وأخفِ زر «حذف حسابي» أو عطّله، لأن إرساله ثانية يرجّع `422`.

**الحالات الثلاث مجتمعة:**

| الوضع | `status` | `deletion_requested` | ما يشوفه المستخدم |
|---|---|---|---|
| حساب عادي | `active` | `false` | كل شي طبيعي |
| طلب الحذف وينتظر | `active` | `true` | شريط «قيد المراجعة» |
| أوقفته الإدارة | `inactive` / `suspended` | أي قيمة | «حسابك غير مفعّل، راجع الإدارة» عند الدخول |

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
| 🌐 | `POST /customer/auth/verify-otp` | تحقق ← توكن |
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
