# خطة مشروع «Hoame Service» — منصة خدمات المنازل

منصّة خدمات منزلية: **لوحة تحكم (Dashboard)** لإدارة كل المحتوى + **API** يغذّي تطبيق الموبايل.
الواجهة الأمامية للوحة التحكم = قالب **Vuexy** الحالي (نبقّيه ونربطه تدريجياً).
الباك اند = **Laravel 12** يُبنى من جديد بمعمارية طبقات نظيفة.

---

## 1. المبادئ الأساسية (Ground Rules)

1. **فصل الحسابات تماماً:** `admins` و `users` و `technicians` — **جداول منفصلة**، ما نخلي الأدمن والمستخدم بنفس الجدول.
2. **API إصداري:** كل المسارات تحت `‎/api/v1‎`.
3. **استجابة موحّدة (Standard Response):** كل الردود بنفس الشكل عبر `ApiResponse` واحد.
4. **معمارية طبقات:** `FormRequest → Controller → Service → Model → Resource → ApiResponse`.
5. **لا تكرار كود (DRY):** منطق واحد للـ DataTable (بحث/فلترة/ترتيب/ترقيم) نعيد استخدامه بكل موديول — بالباك اند وبالفرونت.
6. **تفعيل/إخفاء متسلسل:** إخفاء المحافظة يُخفي أقضيتها تلقائياً (ما تظهر بالتطبيق).
7. **بناء تدريجي موديول-موديول:** ننجّز واحد ونتأكد منه قبل ما ننتقل للي بعده.

---

## 2. الستاك التقني (Target Stack)

| الطبقة | التقنية | متى |
|---|---|---|
| Framework | Laravel 12 · PHP 8.3+ | أساسي |
| Database | MySQL 8 | أساسي |
| Auth | Laravel Sanctum (توكن) | أساسي |
| ORM | Eloquent | أساسي |
| API | API Resources · Form Requests · Versioning · Standard Response · Pagination/Filter/Sort | أساسي |
| Architecture | Services · Actions · Policies · Enums · DTOs · Events/Listeners | أساسي |
| Database tooling | Migrations · Seeders · Factories · DB Transactions | أساسي |
| Storage | Laravel Storage (روابط كاملة للصور) | أساسي |
| Security | Validation · Policies · Rate Limiting · Sanctum | أساسي |
| Performance | Redis · Cache · Queues · Horizon | عند الحاجة (رفع الصور/الإشعارات/OTP بالخلفية) |
| Monitoring | Laravel Log · Telescope · Sentry | تدريجي (Telescope مبكّر للتشخيص، Sentry قبل الإطلاق) |
| Testing | Pest · Feature Tests · Unit Tests | مع كل موديول |

> الفلسفة: نركّب الأساسيات من البداية، والثقيل (Horizon/Sentry/Redis) نفعّله لما يصير له استخدام فعلي — بدون تعقيد مبكّر.

---

## 3. معمارية المجلدات (app/)

```
app/
├── Enums/                 UserStatus · AdminStatus · TechnicianStatus · OrderStatus · ServiceStatus · LegalPageKey · SliderStatus · MediaType
├── Http/
│   ├── Controllers/
│   │   ├── Admin/         تحكّم لوحة التحكم (Auth, Dashboard, Admin, User, Governorate, District, Slider, Category, Service, Blog, LegalPage, Specialization, Technician)
│   │   └── Api/           تحكّم التطبيق (Auth, Governorate, District, Slider, Category, Service, Blog, LegalPage, Technician, Order)
│   ├── Requests/  { Admin/ , Api/ }        تحقّق المدخلات (FormRequest لكل عملية)
│   └── Resources/ { Admin/ , Api/ }        تشكيل الاستجابة (لكل موديل Resource)
├── Models/                Admin · User · Technician · Governorate · District · Category · Service · Slider · BlogPost · LegalPage · Specialization · Order (+ Setting · TechnicianMedia)
├── Services/              منطق الأعمال (AuthService, UserService, GovernorateService, DistrictService, ServiceService, TechnicianService, OrderService, OtpService, SettingService)
├── Actions/               عمليات ذرّية مركّبة (RegisterUserAction, SendOtpAction, VerifyOtpAction, AssignTechnicianAction...)
├── DTOs/                  كائنات نقل بيانات بين الطبقات
├── Policies/              صلاحيات الوصول
├── Events/ · Listeners/   أحداث (UserRegistered, OrderCreated...) ومستمعيها
└── Support/
    └── ApiResponse/ApiResponse.php   المُصدِّر الموحّد للاستجابات
```

**دور كل طبقة باختصار:** الـ `Controller` نحيف (يستقبل الطلب المُتحقّق ويرجّع Resource عبر ApiResponse) · الـ `Service` يحمل منطق الأعمال · الـ `Action` عملية واحدة قابلة لإعادة الاستخدام · الـ `Resource` يوحّد شكل المخرجات · الـ `Enum` يمنع «القيم السحرية».

---

## 4. فصل الحسابات والمصادقة

| الحساب | الجدول | الدخول | Guard |
|---|---|---|---|
| **الأدمن** | `admins` | إيميل + باسورد (لوحة التحكم) | `admin` (Sanctum) |
| **المستخدم** | `users` | رقم الهاتف + باسورد + تحقّق OTP | `user` (Sanctum) |
| **الفني** | `technicians` | يُضاف يدوياً من اللوحة الآن؛ قرار حساب دخول مستقل مؤجّل | — (لاحقاً) |

- **الأدمن:** جدول مستقل `admins(id, name, email, password, status, timestamps)` — دخول لوحة التحكم فقط.
- **الفني:** حالياً يُنشئه الأدمن يدوياً. هل يصير له حساب دخول للتطبيق لاحقاً؟ **نؤجّل القرار** حتى ما نضيف تعقيد بدون حاجة.
- صفحة تسجيل دخول الأدمن الحالية بالقالب نربطها بـ `‎/api/v1/admin/auth/login‎` ونرتّبها.

---

## 5. نموذج البيانات (Database Schema)

### أ. الحسابات
| الجدول | الحقول الأساسية |
|---|---|
| `admins` | id · name · email(unique) · password · status(AdminStatus) · timestamps |
| `users` | id · name · gender(male/female) · phone(unique) · password · **governorate_id(FK)** · **district_id(FK)** · terms_accepted_at · phone_verified_at · status(UserStatus) · timestamps |
| `technicians` | id · name · phone · governorate_id(FK) · district_id(FK) · status(TechnicianStatus) · timestamps |
| `technician_media` | id · technician_id(FK) · type(MediaType: personal / id_front / id_back / residence_front / residence_back / work_sample) · path · sort · timestamps |
| `technician_specialization` | technician_id(FK) · specialization_id(FK) — ربط متعدد (فني ↔ اختصاصات) |
| `phone_verifications` | id · phone · message_id · purpose(register/reset) · verified_at · expires_at |

> صور الفني (الشخصية + وجه/ظهر البطاقة الوطنية + وجه/ظهر بطاقة السكن + 4 نماذج أعمال) كلها في `technician_media` — مرن وقابل للتوسّع بدل أعمدة كثيرة.

### ب. المواقع الجغرافية
| الجدول | الحقول |
|---|---|
| `governorates` | id · name · is_active · sort_order · timestamps |
| `districts` | id · governorate_id(FK) · name · is_active · sort_order · timestamps |

- **عدّاد الزبائن:** عدد الزبائن بكل محافظة/قضاء يُحسب من `users` (المستخدم يختار محافظته/قضاءه عند التسجيل).
- **الإخفاء المتسلسل:** الـ API العامة ترجّع المحافظات المفعّلة فقط، وضمنها الأقضية المفعّلة فقط؛ إخفاء المحافظة يُخفي أقضيتها ضمنياً.

### ج. المحتوى والكتالوج
| الجدول | الحقول |
|---|---|
| `sliders` | id · image · link(nullable) · sort_order · is_active · timestamps |
| `categories` | id · name · image · is_active · sort_order · timestamps |
| `services` | id · category_id(FK) · name · image · description · is_active · sort_order · timestamps |
| `specializations` | id · name · is_active · timestamps |
| `blog_posts` | id · title(اختياري) · image · content · published_at · is_active · timestamps |
| `legal_pages` | id · key(privacy_policy / terms_of_use, unique) · title · content(HTML) · timestamps |

- **إحصائية الخدمات:** عمود محسوب `orders_count` (عدد الطلبات لكل خدمة) يظهر بنفس جدول الـ DataTable.
- **الصفحات القانونية:** صفّان ثابتان يُزرعان بالـ Seeder، محتوى HTML قابل للتعديل، ولها API عام خاص.

### د. الأعمال والنظام
| الجدول | الحقول |
|---|---|
| `orders` | id · **order_number(unique)** · user_id(FK) · service_id(FK) · technician_id(FK,nullable) · governorate_id(FK) · district_id(FK) · description · scheduled_date · time_from · time_to · latitude · longitude · landmark(nullable) · status(OrderStatus) · inspection_note(nullable) · cancelled_by(nullable) · cancelled_at(nullable) · timestamps |
| `order_images` | id · order_id(FK) · path · sort — صور اختيارية (حد أقصى 4) |
| `order_status_histories` | id · order_id(FK) · from_status · to_status · actor_type(admin/user/system) · actor_id · note(nullable) · created_at — سجل أحداث الطلب |
| `settings` | id · key(unique) · value · timestamps *(مفاتيح مثل OTP API Key)* |

> الطلبات تُنشأ من التطبيق (مرحلة لاحقة)، لكن نجهّز الجدول مبكراً لأن إحصائيات الخدمات وقائمة «طلبات الفني» تعتمد عليه.

---

## 6. تصميم الـ API

### الإصدار والتقسيم
```
/api/v1/admin/...            ← لوحة التحكم (guard: admin)
/api/v1/customer/...         ← تطبيق الزبون: تصفّح (categories · services · sliders · blog · legal-pages · governorates · districts) + طلبات + الملف
/api/v1/customer/auth/...    ← تسجيل / دخول / تحقّق OTP / استعادة كلمة السر
```
مسارات التصفّح تحت `customer` = قراءة عامة بدون توكن. مسارات `orders` والملف الشخصي = محمية بتوكن الزبون (Sanctum). لوحة التحكم كلها تحت `admin` بحارس منفصل.

### دورة حياة الطلب
```
Request → FormRequest (validation) → Controller → Service (business logic) → Model → Resource → ApiResponse
```
مثال إنشاء مستخدم: `StoreUserRequest → UserController → UserService → User → UserResource → ApiResponse`.

### الاستجابة الموحّدة
```jsonc
// نجاح
{ "success": true, "message": "Users retrieved successfully.", "data": [] }

// خطأ
{ "success": false, "message": "Validation failed.",
  "errors": { "phone": ["The phone number is already registered."] } }

// DataTable (مع ترقيم)
{ "success": true, "message": "Users retrieved successfully.", "data": [],
  "meta": { "current_page": 1, "per_page": 15, "total": 250, "last_page": 17 } }
```

---

## 7. المكوّنات المشتركة (منع تكرار الكود)

**الباك اند:**
- `Support/ApiResponse` — دوال `success() / error() / paginated()` تُخرج الشكل الموحّد أعلاه.
- طبقة **List موحّدة** (بحث `q` + فلترة + ترتيب `sortBy/orderBy` + ترقيم `page/per_page`) عبر trait/QueryBuilder واحد يستعمله كل كنترولر — نستعين بـ `spatie/laravel-query-builder` لتوحيد الفلترة والترتيب.
- `BaseCrudService` عام للعمليات المتكرّرة (list/create/update/delete/toggleActive/reorder).

**الفرونت (Vuexy):**
- مكوّن جدول موحّد `AppDataTableServer` + composable `useServerTable` يغلّف بارامترات (`q, page, itemsPerPage, sortBy, orderBy`) ويقرأ `meta` — كل صفحة تمرّر الأعمدة + رابط الـ API فقط.
- عنصر رفع صورة موحّد + نمط «درج» موحّد للإضافة/التعديل + ترقيم موحّد (`TablePagination` الموجود).

---

## 8. تكامل OTP (arqam.tech)

مسار تسجيل المستخدم:
1. المستخدم يُدخل: الاسم · الجنس(ذكر/أنثى) · رقم الهاتف · الباسورد + تأكيده · الموافقة على الشروط.
2. ننشئ المستخدم (غير مُتحقّق) ونستدعي `POST /sms/otp` بـ `phoneNumber` فقط — **بدون توليد كود؛ أرقم يولّد ويرسل الكود** — ونخزن `messageId`.
3. صفحة التحقق: المستخدم يُدخل الكود ← نستدعي `POST /sms/verify` بـ `{messageId, code}`.
4. عند النجاح: نضبط `phone_verified_at` ونُصدر توكن Sanctum.

**استعادة كلمة السر (App — «نسيت كلمة السر»):**
1. الزبون يُدخل رقم هاتفه ← `POST /api/v1/customer/auth/forgot-password`.
2. نستدعي `POST /sms/reset-password` (أرقم) — بدون توليد كود؛ يوصله كود — ونخزن `messageId`.
3. صفحة كلمة سر جديدة: يُدخل الكود + كلمة السر الجديدة + تأكيدها ← `POST /api/v1/customer/auth/reset-password` (نتحقّق عبر `POST /sms/verify` ثم نحدّث كلمة السر).

- **مفتاح الـ API** يُخزّن ويُقرأ من جدول `settings` (صفحة الإعدادات تعدّله)، مع إمكانية بذرة أولية من `.env`.
- Rate Limiting على مسارات الإرسال/التحقق، وربط أكواد الأخطاء (INVALID_PHONE, EXPIRED_CODE, INVALID_CODE...) برسائل عربية موحّدة.
- استدعاءات أرقم داخل `OtpService` + `Action` مع منطق الأخطاء والـ Queue عند الحاجة.

---

## 9. الوحدات (Modules)

| # | الموديول | صفحات اللوحة | أهم الحقول/القواعد |
|---|---|---|---|
| A | **مصادقة الأدمن** | صفحة دخول | إيميل+باسورد، ربط Sanctum، ترتيب الصفحة |
| B | **المحافظات** | قائمة (DataTable) | اسم · تفعيل/إخفاء · عدّاد زبائن · ترتيب |
| C | **الأقضية** | قائمة (DataTable) | اسم · تابع لمحافظة · تفعيل/إخفاء (متسلسل) · عدّاد زبائن |
| D | **السلايدرات** | قائمة/شبكة | صورة · (صورة+رابط) · ترتيب · حذف · تفعيل |
| E | **الأقسام** | قائمة (DataTable) | اسم · صورة · تفعيل · تعديل · حذف |
| F | **الخدمات** | قائمة (DataTable) | اسم · اختيار قسم · صورة · وصف · تفعيل · تعديل · حذف · عدّاد طلبات |
| G | **الاختصاصات** | قائمة (DataTable) | اسم · تفعيل · تعديل · حذف |
| H | **المدوّنة** | قائمة + نموذج | صورة واحدة · نص · تاريخ نشر · تعديل · حذف |
| I | **الصفحات القانونية** | تحرير صفّين | سياسة الخصوصية · شروط الاستخدام (HTML) · API عام |
| J | **الفنيون** | صفحة إضافة + قائمة | نموذج يدوي (اسم·هاتف·محافظة·قضاء·6 صور هوية+شخصية+4 نماذج) · قائمة تفاصيل · عند الدخول: DataTable لطلبات الفني |
| K | **المستخدمون** | قائمة | تسجيل ذاتي من التطبيق + OTP · عدّادات |
| K2 | **استمارة انضمام الفنيين** | استمارة عامة + قائمة وتفاصيل | تقديم بلا حساب · حالات (معلّق · قيد المراجعة · مقبول · مرفوض) · القبول يُنشئ الفني · مفتاح تشغيل بالإعدادات — *تفصيل بالقسم 9.2* |
| L | **الطلبات (طلب الخدمة)** | قائمة · تفاصيل · إدارة حالات | إنشاء من التطبيق · حالات يديرها الأدمن · تعيين فني حسب المحافظة · سجل أحداث · زر واتساب · رقم طلب — *تفصيل بالقسم 9.1* |
| M | **لوحة الإحصائيات** | Dashboard | ملخّصات (طلبات/خدمات/مستخدمون/فنيون) |
| N | **الإعدادات** | صفحة إعدادات | مفاتيح API (OTP) تُحفظ وتُقرأ بأمان |

---

### 9.1 موديول الطلبات (طلب الخدمة) — بالتفصيل

**إنشاء الطلب (من التطبيق):**
- المستخدم يضغط على الخدمة ← يكتب **وصف الخدمة**.
- يختار **وقتاً**: نافذة ساعة واحدة (من – إلى) فقط. لا تظهر الأوقات الماضية لليوم الحالي، مع تمييز صباحاً/مساءً، والأوقات متاحة على مدار اليوم كامل.
- يرفق **صوراً اختيارية** (حد أقصى 4).
- يحدّد موقعه على **الخريطة داخل التطبيق** ← تطلع `latitude/longitude` + `landmark` (معلم قريب) وتُرسل **مع نفس نداء الطلب** — لا يوجد نداء موقع منفصل.
- يضغط **إرسال** ← يُنشأ الطلب بـ **رقم طلب فريد** ويظهر له «شكراً».

**جانب المستخدم:**
- صفحة **«طلباتي»** تعرض طلباته وحالاتها.
- يقدر يلغي الطلب **فقط** وهو «معلّق»؛ بعد **تأكيد الطلب** ما يقدر يلغي.

**دورة حالة الطلب — يديرها الأدمن فقط:**

| # | الحالة | المعنى/الإجراء |
|---|---|---|
| 1 | `pending` معلّق | الزبون يقدر يلغي |
| 2 | `confirmed` مؤكّد | يُقفل إلغاء الزبون |
| 3 | `assigned` تعيين فني | اختيار فني **من نفس محافظة الطلب فقط** |
| 4 | `inspected` تم الكشف | مع كتابة نص/ملاحظة |
| 5 | `completed` تم إنجاز الخدمة | — |
| — | `cancelled` ملغى | من الزبون وهو معلّق |

**صفحة تفاصيل الطلب (اللوحة):** تفاصيل الطلب ومعلوماته + اسم الفني المعيَّن + **سجل أحداث** لكل تغيّر حالة (ماذا تغيّر، من نفّذه، ومتى) + **زر واتساب** يفتح محادثة مباشرة مع الفني أو الزبون ومعها رقم الطلب.

**قواعد الأعمال:** رقم طلب فريد لكل طلب · تغيير الحالة حصراً من الأدمن (عدا إلغاء الزبون وهو معلّق) · تعيين الفني مقيّد بمحافظة الطلب · كل انتقال حالة يُسجَّل في `order_status_histories` مع منفّذه.

**أهم المسارات:**
- تطبيق (الزبون): `POST /api/v1/customer/orders` · `GET /api/v1/customer/orders` (طلباتي) · `GET /api/v1/customer/orders/{id}` · `POST /api/v1/customer/orders/{id}/cancel`
- لوحة: `GET /api/v1/admin/orders` · `GET /api/v1/admin/orders/{id}` · `POST /api/v1/admin/orders/{id}/confirm` · `POST /api/v1/admin/orders/{id}/assign-technician` · `POST /api/v1/admin/orders/{id}/inspect` · `POST /api/v1/admin/orders/{id}/complete`

نموذج جسم إنشاء الطلب (الإحداثيات مضمّنة، بدون نداء موقع منفصل — إضافة لنافذة الوقت والصور الاختيارية):
```json
POST /api/v1/customer/orders
{
  "service_id": 12,
  "description": "المكيّف لا يبرّد",
  "latitude": 33.7465,
  "longitude": 44.3908,
  "landmark": "قرب جامع الرحمن"
}
```

---

### 9.2 موديول استمارة انضمام الفنيين — بالتفصيل

**الفكرة:** صفحة داخل التطبيق يقدّم منها أي شخص طلب انضمام كفني. الطلب يوصل للوحة كـ«استمارة» لها دورة حالة، وعند القبول **يتحوّل تلقائياً إلى فني** بصفحة الفنيين مع كل صوره. الاستمارة كلها **تُشغَّل وتُطفأ من الإعدادات**.

**من يقدّم:** أي زائر للتطبيق — **بدون تسجيل دخول**. النقطة عامة تماماً مثل تصفّح الأقسام والخدمات. ولو كان الزبون مسجّلاً دخوله فما يفرق شي (التوكن يُتجاهل).

#### حقول الاستمارة

| الحقل | النوع | القيد |
|---|---|---|
| `full_name` | نص | **الاسم الثلاثي** — 3 كلمات على الأقل، 5–120 حرف |
| `phone` | نص | مطلوب · صيغة عراقية · يُخزَّن بصيغة `9647…` · **فريد — هو مفتاح منع التكرار** |
| `governorate_id` | مرجع | من محافظات النظام المفعّلة |
| `district_id` | مرجع | من أقضية **نفس المحافظة** فقط |
| `specialization_ids[]` | مصفوفة | اختصاص واحد على الأقل من اختصاصات النظام المفعّلة |

#### المرفقات — نفس `MediaType` الموجود، بلا إنشاء أنواع جديدة

| المفتاح | العدد | إلزامي |
|---|---|---|
| `personal` صورة شخصية | 1 | ✅ |
| `id_front` وجه البطاقة الوطنية | 1 | ✅ |
| `id_back` ظهر البطاقة الوطنية | 1 | ✅ |
| `residence_front` وجه بطاقة السكن | 1 | ✅ |
| `residence_back` ظهر بطاقة السكن | 1 | ✅ |
| `work_samples[]` نماذج أعمال | حتى 4 | ❌ اختيارية |

الصيغ `jpg/jpeg/png/webp` والحد 4 ميغا للصورة — نفس قيود رفع صور الفني الحالية.

#### دورة الحالة

القبول **ليس حالة** — هو إجراء يحوّل الاستمارة إلى فني ويحذفها. فالحالات ثلاث فقط:

| # | الحالة | المعنى | ينتقل إلى |
|---|---|---|---|
| 1 | `pending` معلّق | وصلت للتو، ما فُتحت بعد | قيد المراجعة · مرفوض |
| 2 | `under_review` قيد المراجعة | الأدمن يدقّق الأوراق | مرفوض |
| 3 | `rejected` تم الرفض | مع سبب إلزامي يُكتب | قيد المراجعة (لو انرفضت غلطاً) |

وفوقهن **زر «قبول»** متاح بأي حالة، وهو `POST /{id}/accept`. كل تغيّر حالة يُسجَّل مع منفّذه ووقته (`reviewed_by` + `reviewed_at`).

#### عند القبول — ماذا يحدث بالضبط

كله داخل **معاملة واحدة**، ولو فشلت أي خطوة يُلغى كل شيء وتُرجَّع الملفات لمكانها:

1. فحص التعارض: لو الرقم موجود أصلاً كفني ← رفض القبول برسالة واضحة.
2. إنشاء `Technician` بالاسم والهاتف والمحافظة والقضاء، بحالة **`pending` قيد الانتظار** و`source = application`.
3. مزامنة الاختصاصات على `technician_specialization`.
4. **نقل ملفات الصور** من `applications/{id}/` إلى `technicians/{id}/` وإنشاء صفوف `technician_media` — بدون إعادة رفع.
5. **حذف الاستمارة نهائياً** — صفّها وصفوف صورها وربط اختصاصاتها. الملفات تبقى، صارت ملك الفني.
6. الواجهة تنقلك مباشرة لملف الفني الناتج.

**محسوم — الاستمارة ما تبقى بعد القبول.** بياناته راحت للفنيين وخلص؛ ما في سجل «مقبول» يتراكم بصفحة الاستمارات. والي يتذكّر إنه اجا من الاستمارة هو عمود `technicians.source`.

**محسوم — الفني يجي `قيد الانتظار` مو نشطاً.** أوراقه مكتملة، بس التفعيل يبقى بيدك: تفتح ملفه، تراجعه، وتضغط «نشط» حتى يبدأ يستلم طلبات. وحارس التفعيل الموجود (`لا يمكن تفعيل الفني قبل رفع: …`) يبقى شغّالاً مثل ما هو.

#### تمييز مصدر الفني — «من اللوحة» أم «من استمارة»

عمود على `technicians`:

| العمود | القيم | الافتراضي |
|---|---|---|
| `source` | `manual` أُضيف من اللوحة · `application` جاء من استمارة | `manual` |

بما إن الاستمارة تنحذف عند القبول، هذا العمود هو **الأثر الوحيد**، ولذلك خُزّن كعمود حقيقي لا كاستنتاج من رابط.

- **قائمة الفنيين**: عمود «المصدر» بشارة ملوّنة + فلتر عليه.
- **ملف الفني**: نفس الشارة ببطاقة الرأس.

#### مفتاح التشغيل والإيقاف

إعداد جديد `technician_application_open` بجدول `settings`، مجموعة **«استمارة الفنيين»**، من نوع **بولياني** (مفتاح تبديل بصفحة الإعدادات — النوع الحالي نص/سرّ فقط فيحتاج إضافة نوع ثالث).

- مطفأ ← `GET` يرجّع `is_open: false` فالتطبيق يخفي المدخل أصلاً، و`POST` يُرفض بـ **422**: «التسجيل كفني مغلق حالياً».
- الافتراضي عند أول تشغيل: **مفتوح**.

#### رسالة النجاح

يرجّعها السيرفر بـ `message` فما ينكتب النص بالتطبيق:

> **شكراً على إرسال طلبك، سيتم الاتصال بك**

#### جداول قاعدة البيانات

| الجدول | الأعمدة |
|---|---|
| `technicians` (تعديل) | إضافة عمود `source` (`manual` \| `application`، افتراضي `manual`) |
| `technician_applications` | `id` · `full_name` · `phone` (**فريد**) · `governorate_id`(FK) · `district_id`(FK) · `status` · `note` (سبب الرفض/ملاحظة) · `reviewed_by`(FK admins، nullable) · `reviewed_at` · `timestamps` |
| `technician_application_specialization` | `application_id`(FK) · `specialization_id`(FK) |
| `technician_application_media` | `id` · `application_id`(FK) · `type` (نفس `MediaType`) · `path` · `sort` |

#### المسارات

**عامة (بلا توكن):**
- `GET /api/v1/customer/technician-application` ← `{ is_open, required_documents[], work_samples_limit, max_file_mb }`
- `GET /api/v1/customer/specializations` ← الاختصاصات المفعّلة (**نقطة جديدة** — حالياً الاختصاصات للأدمن فقط)
- `POST /api/v1/customer/technician-applications` ← `multipart/form-data`

> المحافظات والأقضية موجودة عامة أصلاً: `GET /customer/governorates` و`GET /customer/governorates/{id}/districts`.

**اللوحة:**
- `GET /api/v1/admin/technician-applications` — قائمة + فلاتر (حالة · محافظة · اختصاص) + بحث بالاسم أو الهاتف
- `GET /api/v1/admin/technician-applications/{id}` — التفاصيل مع كل الصور
- `PATCH /api/v1/admin/technician-applications/{id}/status` — `{ status, note? }` لقيد المراجعة والرفض
- `POST /api/v1/admin/technician-applications/{id}/accept` — يُنشئ الفني ويحذف الاستمارة، ويرجّع الفني الناتج
- `DELETE /api/v1/admin/technician-applications/{id}` — مع حذف ملفاتها
- `GET /api/v1/admin/technician-applications/pending-count` — عدّاد المعلّقة

صلاحيات جديدة بالـSeeder: `technician-applications.view` · `.manage` · `.delete`.

#### شاشات اللوحة

- **قائمة** `/admin/technician-applications` — DataTable موحّد: الاسم · الهاتف · المحافظة/القضاء · الاختصاصات · الحالة · تاريخ التقديم · زر عرض. مع فلتر حالة وبحث.
- **تفاصيل** `/admin/technician-applications/{id}` — بطاقة رأس بالحالة، بيانات المتقدّم، شبكة الصور الست/التسع بمعاينة مكبّرة، **زر واتساب** للتواصل معه، وأزرار: **قيد المراجعة · قبول · رفض (مع كتابة السبب) · حذف**. القبول يسأل تأكيداً ثم ينقلك مباشرة لملف الفني الناتج.
- **القائمة الجانبية** — تحت «الأشخاص»، مع **عدّاد الطلبات المعلّقة** بجانب الاسم.

#### منع التكرار — بالرقم، بلا توثيق OTP

**محسوم:** ما في رمز تحقّق قبل الإرسال. رقم الهاتف نفسه هو المفتاح:

- الرقم عنده استمارة سابقة **بأي حالة** ← **422**: «تم التسجيل مسبقاً بهذا الرقم».
- الرقم مسجّل أصلاً كفني (بضمنه من قُبل من استمارة) ← **422**: «هذا الرقم مسجّل كفني بالفعل».
- المخرج الوحيد لإعادة التقديم: الأدمن يحذف الاستمارة من اللوحة (`DELETE`) فيتحرّر الرقم.

> **نتيجتان لازم تكون منتبه لهن:** (١) المرفوض ما يقدر يعيد التقديم بنفسه أبداً — لازم الأدمن يحذف استمارته أولاً. (٢) بما إن ما في توثيق، أي شخص يقدر يقدّم برقم غيره ويقفل عليه باب التقديم؛ الحل نفسه — حذف الاستمارة من اللوحة.

**توحيد صيغة الهاتف:** كل الهواتف بالنظام تُخزَّن `9647…`. كانت هواتف الفنيين تُخزَّن كما يكتبها الأدمن (`0771…`) فيفشل هذا الفحص بصمت وتنكسر قاعدة `unique` نفسها — صار التوحيد عند حدود الطلب مع مايكريشن لتصحيح الصفوف القديمة.

#### قواعد الحماية (النقطة عامة، فلازم)

- **تحديد معدّل**: 3 استمارات بالساعة لكل IP + 3 لكل رقم هاتف.
- **حجم الرفع**: 9 صور × 4 ميغا = 36 ميغا بالطلب الواحد — لازم `post_max_size` و`upload_max_filesize` بالسيرفر تستوعبها، وإلا يفشل الرفع بصمت.
- المرفقات تُخزَّن بمجلد الاستمارة، وتنمسح كلها لو فشلت المعاملة أو انحذفت الاستمارة.

#### القرارات — كلها محسومة

| القرار | الحسم |
|---|---|
| توثيق الهاتف بـOTP | **لا** — الرقم وحده يمنع التكرار |
| منع التكرار | استمارة سابقة بأي حالة ← «تم التسجيل مسبقاً بهذا الرقم» |
| حالة الفني عند القبول | **`pending` قيد الانتظار** — التفعيل يدوي من ملفه |
| مصير الاستمارة بعد القبول | **تُحذف نهائياً** — البيانات راحت للفنيين وخلص |
| تمييز المصدر | عمود `source` على `technicians` + شارة بالقائمة وبالملف |
| **إشعار المتقدّم** | **لا يوجد** — ما عنده حساب بالتطبيق، والتواصل معه **يدوي** (اتصال أو واتساب من اللوحة) |

بما إنه ما في إشعار للمتقدّم، **زر واتساب** بصفحة تفاصيل الاستمارة هو وسيلة التواصل الوحيدة — يفتح محادثة مباشرة مع رقمه، مثل زر واتساب بصفحة الطلب.

---

## 10. ترتيب التنفيذ المقترح (Phases)

| المرحلة | المحتوى | تعتمد على |
|---|---|---|
| **0 — الأساس** | Laravel 12 + MySQL + Sanctum + هيكل المعمارية (Enums/Services/Actions/Support) + ApiResponse + طبقة DataTable الموحّدة + Storage + **دخول الأدمن (باك+فرونت)** | — |
| **1 — المواقع** | المحافظات + الأقضية (CRUD · تفعيل/إخفاء متسلسل · عدّادات) + صفحتيهما | 0 |
| **2 — السلايدرات** | CRUD + ترتيب + رابط | 0 |
| **3 — الأقسام** | CRUD كامل | 0 |
| **4 — الخدمات** | CRUD + ربط بالأقسام + عدّاد الطلبات | 3 (+L) |
| **5 — الاختصاصات** | CRUD | 0 |
| **6 — المدوّنة** | CRUD | 0 |
| **7 — الصفحات القانونية** | تحرير + API عام | 0 |
| **8 — الفنيون** | نموذج الإضافة (الوسائط) + القائمة + طلبات الفني | 1 (+L) |
| **9 — المستخدمون + OTP + الإعدادات** | تسجيل ذاتي + تكامل أرقم + صفحة المفاتيح | 0, N |
| **10 — الطلبات + الإحصائيات** | إنشاء الطلب (تطبيق) + إدارة اللوحة (حالات · تعيين فني حسب المحافظة · سجل أحداث · واتساب · رقم طلب) + الإحصائيات | 4, 8, 9 |
| **11 — استمارة انضمام الفنيين** | نقطة عامة + استمارة بالتطبيق + قائمة وتفاصيل باللوحة + دورة حالة + تحويل المقبول إلى فني + مفتاح تشغيل بالإعدادات — *تفصيل بالقسم 9.2* | 1, 5, 8 |

> نبدأ عملياً بالمرحلة 0 ثم نمشي واحدة-واحدة حسب ترتيبك أنت.

---

## 11. القرارات المثبّتة

1. **موقع الزبون:** المستخدم يختار المحافظة/القضاء عند التسجيل ← `users.governorate_id` + `users.district_id`، والعدّادات تُحسب مباشرة.
2. **اختصاص الفني:** اختصاصات متعددة ← جدول ربط `technician_specialization` (many-to-many).
3. **الإعدادات:** مفاتيح الـ API تُخزّن بجدول `settings` وتُقرأ منه.
4. **حالة التفعيل:** `is_active` (بولياني) للموديولات ذات «مفعّل/غير مفعّل»؛ Enum فقط للحسابات والطلبات (AdminStatus · UserStatus · TechnicianStatus · OrderStatus).

**مؤجّل بوعي:** هل يصير للفني حساب دخول مستقل للتطبيق.

**محسوم:** الخريطة داخل التطبيق فقط — الباك اند يستقبل `latitude/longitude/landmark` مضمّنة بنداء إنشاء الطلب، بدون نداء موقع منفصل.
