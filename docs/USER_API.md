# مستند API کاربری — پلتفرم ایثار (رزرو اقامتگاه)

> **نسخه:** 1.1.0  
> **تاریخ:** ۱۴۰۵/۰۵/۰۱  
> **دامنه:** فقط بخش مهمان/کاربر (`/`) — بدون پنل ادمین و میزبان

---

## API Bearer Token (Sanctum) — جدید

از نسخه ۱.۱، API اختصاصی با **Laravel Sanctum** و احراز هویت **Bearer Token** اضافه شده است.

| مورد | مقدار |
|------|-------|
| Base URL | `/api/v1` |
| احراز هویت | `Authorization: Bearer {token}` |
| دریافت توکن | `POST /api/v1/auth/otp/verify` |
| انقضای توکن | ۳۰ روز (قابل تنظیم با `API_TOKEN_EXPIRATION_DAYS`) |
| Postman | `docs/postman/Eisar-User-API-Sanctum.postman_collection.json` |

### جریان احراز هویت

```
POST /api/v1/auth/otp/send     { "mobile": "09120000001" }
POST /api/v1/auth/otp/verify   { "mobile": "09120000001", "otp": "123456", "device_name": "iphone" }
→ { "token": "1|abc...", "token_type": "Bearer", "expires_at": "...", "user": {...} }

GET  /api/v1/auth/me           Authorization: Bearer {token}
POST /api/v1/auth/logout       (لغو توکن فعلی)
DELETE /api/v1/auth/tokens     (لغو همه توکن‌ها)
```

### Endpointهای v1

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/v1/auth/otp/send` | ❌ |
| POST | `/api/v1/auth/otp/verify` | ❌ |
| GET | `/api/v1/auth/me` | ✅ |
| POST | `/api/v1/auth/logout` | ✅ |
| DELETE | `/api/v1/auth/tokens` | ✅ |
| GET | `/api/v1/provinces` | ❌ |
| GET | `/api/v1/provinces/{id}/cities` | ❌ |
| GET | `/api/v1/locations` | ❌ |
| GET | `/api/v1/accommodation-types` | ❌ |
| GET | `/api/v1/accommodations` | ❌ |
| GET | `/api/v1/accommodations/{id}` | ❌ |
| GET | `/api/v1/accommodations/{id}/rooms-availability` | ❌ |
| GET | `/api/v1/accommodations/{id}/physical-rooms` | ❌ |
| GET | `/api/v1/room-types/{id}/availability` | ❌ |
| GET | `/api/v1/room-types/{id}/physical-rooms` | ❌ |
| GET | `/api/v1/profile` | ✅ |
| PUT | `/api/v1/profile` | ✅ |
| POST | `/api/v1/profile/verify-national-id` | ✅ |
| GET | `/api/v1/bookings` | ✅ |
| GET | `/api/v1/bookings/{id}` | ✅ |
| POST | `/api/v1/accommodations/{id}/bookings` | ✅ |
| GET | `/api/v1/bookings/{id}/pdf` | ✅ |
| GET | `/api/v1/bookings/{id}/cancellation-reasons` | ✅ |
| GET | `/api/v1/bookings/{id}/cancellation-preview` | ✅ |
| POST | `/api/v1/bookings/{id}/cancellation-requests` | ✅ |
| GET | `/api/v1/favorites` | ✅ |
| POST | `/api/v1/favorites/{id}/toggle` | ✅ |
| POST | `/api/v1/accommodations/{id}/reviews` | ✅ |

### ثبت رزرو API (`POST /api/v1/accommodations/{id}/bookings`)

```json
{
  "check_in": "2026-08-10",
  "check_out": "2026-08-13",
  "guests": 3,
  "children_under_6": 1,
  "room_type_id": 1,
  "room_rate_id": 1,
  "extra_guests": 0,
  "bill_full_rooms": false
}
```

| فیلد | نوع | الزامی | توضیح |
|------|-----|--------|--------|
| `guests` | int | ✅ | تعداد کل مهمانان |
| `children_under_6` | int | ❌ | تعداد کودک زیر ۶ سال (باید کمتر از `guests` باشد) |
| `extra_guests` | int | ❌ | نفر اضافه (کف‌خوابی) |
| `bill_full_rooms` | bool | ❌ | رزرو کامل اتاق |

> کودک زیر ۶ سال طبق سیاست اقامتگاه تخفیف می‌گیرد (پیش‌فرض ۵۰٪).

### انواع اقامتگاه (`Accommodation Types`)

هر اقامتگاه یک فیلد `type` (کلید انگلیسی) و `type_label` (برچسب فارسی) دارد. انواع از جدول `accommodation_types` خوانده می‌شوند و توسط ادمین قابل گسترش هستند.

#### `GET /api/v1/accommodation-types`

**احراز هویت:** ❌ عمومی

**پاسخ `200`:**

```json
{
  "data": [
    { "key": "hotel", "label": "هتل", "is_system": true },
    { "key": "villa", "label": "ویلا", "is_system": true },
    { "key": "apartment", "label": "آپارتمان", "is_system": true },
    { "key": "hostel", "label": "باغ ویلا", "is_system": true },
    { "key": "traditional", "label": "اقامتگاه سنتی", "is_system": true }
  ]
}
```

| فیلد | نوع | توضیح |
|------|-----|--------|
| `key` | string | شناسه یکتا — در فیلتر `type` و فیلد `type` اقامتگاه استفاده می‌شود |
| `label` | string | نام فارسی نمایشی |
| `is_system` | bool | `true` برای انواع پیش‌فرض سیدر؛ `false` برای انواع سفارشی ادمین |

**انواع پیش‌فرض سیستم:**

| `key` | `label` |
|-------|---------|
| `hotel` | هتل |
| `villa` | ویلا |
| `apartment` | آپارتمان |
| `hostel` | باغ ویلا |
| `traditional` | اقامتگاه سنتی |

#### فیلتر نوع در لیست اقامتگاه‌ها

```
GET /api/v1/accommodations?type=hotel
```

| پارامتر | نوع | توضیح |
|---------|-----|--------|
| `type` | string | فیلتر بر اساس `key` نوع — مقادیر معتبر از `/accommodation-types` |

**خطا `422` (نوع نامعتبر):**

```json
{
  "message": "نوع اقامتگاه معتبر نیست.",
  "errors": { "type": ["نوع اقامتگاه معتبر نیست."] }
}
```

#### فیلدهای نوع در پاسخ اقامتگاه

در `AccommodationResource` (لیست، جزئیات، علاقه‌مندی‌ها):

| فیلد | نوع | مثال |
|------|-----|------|
| `type` | string | `"hotel"` |
| `type_label` | string | `"هتل"` |

#### سایر فیلترهای `GET /api/v1/accommodations`

| پارامتر | نوع | توضیح |
|---------|-----|--------|
| `province_id` | int | فیلتر استان |
| `city_id` | int | فیلتر شهر |
| `check_in` | date | تاریخ ورود (فیلتر دسترسی) |
| `check_out` | date | تاریخ خروج |
| `guests` | int | حداقل ظرفیت |
| `wheelchair` | bool | `true` → فقط اقامتگاه‌های مناسب ویلچر |
| `lat`, `lng` | float | جستجوی جغرافیایی |
| `radius` | int | شعاع کیلومتر (۱–۵۰۰، پیش‌فرض ۳۰) |
| `per_page` | int | تعداد در صفحه (۱–۵۰، پیش‌فرض ۱۲) |

> مسیرهای قدیمی Session-based (`/login`, `/favorites/.../toggle` و ...) همچنان برای وب فعال هستند.

---

## فهرست

1. [معماری کلی](#معماری-کلی)
2. [احراز هویت و نشست](#احراز-هویت-و-نشست)
3. [هدرهای مشترک](#هدرهای-مشترک)
4. [APIهای JSON (بدون Livewire)](#apiهای-json-بدون-livewire)
5. [Endpointهای Form POST](#endpointهای-form-post)
6. [صفحات و اکشن‌های Livewire](#صفحات-و-اکشنهای-livewire)
7. [مینی‌اپ بله](#مینیاپ-بله)
8. [مدل‌های داده](#مدلهای-داده)
9. [قوانین کسب‌وکار](#قوانین-کسبوکار)
10. [کدهای خطا](#کدهای-خطا)
11. [مثال‌های درخواست](#مثالهای-درخواست)
12. [تست و دیباگ](#تست-و-دیباگ)
13. [ایرادات شناسایی و رفع‌شده](#ایرادات-شناسایی-و-رفعشده)

---

## معماری کلی

پلتفرم ایثار برای کاربر نهایی از **سه لایه** تشکیل شده است:

| لایه | توضیح | پروتکل |
|------|--------|--------|
| **صفحات SSR + Livewire** | رندر اولیه HTML + تعاملات SPA-like | Session Cookie |
| **Form POST کلاسیک** | رزرو، پروفایل، نظر | Session + CSRF |
| **API JSON** | دسترسی، شهرها، علاقه‌مندی | Session یا عمومی |

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  مرورگر     │────▶│  Laravel Routes  │────▶│  Controllers /  │
│  (کاربر)    │◀────│  (web.php)       │◀────│  Livewire       │
└─────────────┘     └──────────────────┘     └─────────────────┘
       │                                              │
       │  fetch('/api/...')                           │
       └──────────────────────────────────────────────┘
```

**Base URL (توسعه):** `http://127.0.0.1:8000`  
**Base URL (تولید):** دامنه اصلی سایت

> ⚠️ این پروژه **API REST جداگانه با توکن Bearer** ندارد. احراز هویت مبتنی بر **Session Cookie** است (`laravel_session`).

---

## احراز هویت و نشست

### روش ورود: OTP موبایل

کاربر با شماره موبایل `09xxxxxxxxx` وارد می‌شود. در محیط توسعه، OTP ثابت **`123456`** برای همه کاربران پذیرفته می‌شود.

#### جریان ورود

```
GET  /login              → فرم شماره موبایل
POST /login              → ارسال OTP (ذخیره mobile در session)
GET  /login/verify       → فرم کد ۶ رقمی
POST /login/verify       → تأیید OTP + ایجاد/ورود کاربر
```

#### `POST /login` — ارسال OTP

| فیلد | نوع | الزامی | قوانین |
|------|-----|--------|--------|
| `mobile` | string | ✅ | `^09[0-9]{9}$` |
| `_token` | string | ✅ | CSRF token |

**پاسخ موفق:** Redirect `302` → `/login/verify`  
**خطاها:**

| پیام | علت |
|------|-----|
| شماره موبایل الزامی است. | فیلد خالی |
| شماره موبایل معتبر نیست. مثال: 09123456789 | فرمت نادرست |

---

#### `POST /login/verify` — تأیید OTP

| فیلد | نوع | الزامی | قوانین |
|------|-----|--------|--------|
| `otp` | string | ✅ | دقیقاً ۶ رقم |
| `_token` | string | ✅ | CSRF |

**منطق:**

1. OTP=`123456` همیشه پذیرفته می‌شود (میانبر تست)
2. در غیر این صورت از پکیج `ichtrojan/otp` اعتبارسنجی می‌شود
3. کاربر `firstOrCreate` بر اساس `mobile`
4. `mobile_verified_at` تنظیم می‌شود
5. `Auth::login($user)` — برای کاربران staff، remember=false
6. اگر `name` خالی باشد → redirect به `/profile/setup`
7. در غیر این صورت → redirect به `/` (یا intended URL)

**پاسخ موفق:** Redirect `302`

| پیام | علت |
|------|-----|
| کد وارد شده نامعتبر یا منقضی شده است. | OTP اشتباه |

---

#### `POST /logout` — خروج

| Middleware | `auth` |
|------------|--------|

**پاسخ:** Redirect — در حالت `STAFF_ONLY_MODE` به `/admin/login`، در غیر این صورت به `/`

---

### کاربر تست (Seeder)

| فیلد | مقدار |
|------|-------|
| موبایل | `09120000001` |
| OTP | `123456` |
| نام | رضا صادقی |
| تخفیف | ۴۰٪ (جانباز ۲۵–۴۹) |

---

## هدرهای مشترک

### درخواست‌های Form POST

```http
Content-Type: application/x-www-form-urlencoded
X-CSRF-TOKEN: {csrf_token}
Cookie: laravel_session={session_id}
```

توکن CSRF از `<meta name="csrf-token">` در layout گرفته می‌شود.

### درخواست‌های JSON (fetch)

```http
Accept: application/json
X-CSRF-TOKEN: {csrf_token}    # برای POSTهای احراز هویت‌شده
Cookie: laravel_session={session_id}
```

### درخواست‌های Livewire

Livewire به‌صورت خودکار هدر `X-Livewire` و CSRF را مدیریت می‌کند.  
Endpoint داخلی: `POST /livewire/update`

---

## APIهای JSON (بدون Livewire)

### ۱. لیست شهرهای یک استان

```
GET /api/provinces/{province_id}/cities
```

| پارامتر | محل | نوع | توضیح |
|---------|-----|-----|--------|
| `province_id` | path | integer | شناسه استان |

**احراز هویت:** ❌ عمومی

**پاسخ `200`:**

```json
[
  { "id": 1, "name": "تهران" },
  { "id": 2, "name": "شهریار" }
]
```

**کنترلر:** `AccommodationController@citiesByProvince`

---

### ۲. نقشه دسترسی نوع اتاق (تقویم)

```
GET /api/room-types/{room_type_id}/availability
```

| پارامتر | محل | نوع | پیش‌فرض | توضیح |
|---------|-----|-----|---------|--------|
| `months` | query | string | ماه جاری + ۲ ماه بعد | لیست `YYYY-MM` جدا شده با کاما (حداکثر ۶ ماه) |
| `room_rate_id` | query | integer | — | تعرفه انتخابی برای قیمت‌گذاری |

**احراز هویت:** ❌ عمومی (فقط اتاق/اقامتگاه فعال)

**پاسخ `200`:**

```json
{
  "dates": {
    "2026-08-01": {
      "total": 5,
      "booked": 2,
      "blocked_rooms": 0,
      "available_rooms": 3,
      "is_blocked": false,
      "is_partially_blocked": false,
      "has_override": false,
      "override_count": null,
      "default_price": 500000,
      "base_price": 500000,
      "custom_price": null,
      "discount_percentage": null,
      "price_label": null,
      "effective_price": 500000,
      "has_price_override": false,
      "price_source": "default",
      "has_weekly_rule": false,
      "rate_price_overrides": {}
    }
  }
}
```

**نکات:**
- فقط تاریخ‌های از امروز به بعد برگردانده می‌شود
- اگر `room_type` یا `accommodation` غیرفعال باشد: `{"dates":{}}`

---

### ۳. دسترسی همه انواع اتاق یک اقامتگاه

```
GET /api/accommodations/{accommodation_id}/rooms-availability
```

| پارامتر | محل | نوع | الزامی |
|---------|-----|-----|--------|
| `check_in` | query | `Y-m-d` | ✅ |
| `check_out` | query | `Y-m-d` | ✅ |

**محدودیت:** حداکثر **۳۶۵ شب** (`StayDurationPicker::MAX_NIGHTS`)

**پاسخ `200`:**

```json
{
  "1": {
    "min_available": 3,
    "is_available": true,
    "room_count": 5,
    "capacity": 2
  },
  "2": {
    "min_available": 0,
    "is_available": false,
    "room_count": 4,
    "capacity": 3
  }
}
```

**پاسخ نامعتبر (تاریخ اشتباه یا بازه بلند):** `{}`

---

### ۴. اتاق‌های فیزیکی یک نوع اتاق

```
GET /api/room-types/{room_type_id}/physical-rooms
```

| پارامتر | محل | نوع | توضیح |
|---------|-----|-----|--------|
| `check_in` | query | `Y-m-d` | الزامی |
| `check_out` | query | `Y-m-d` | الزامی |
| `exclude_room_ids` | query | string | شناسه‌ها با کاما، مثلاً `1,2,3` |

**پاسخ `200`:**

```json
{
  "rooms": [
    {
      "id": 1,
      "name": "اتاق ۱۰۱",
      "description": null,
      "amenities": ["تلویزیون"],
      "sort_order": 1,
      "status": "available",
      "selectable": true,
      "status_label": "آزاد",
      "color": "#22c55e"
    }
  ],
  "room_type": {
    "id": 1,
    "name": "دو تخته"
  }
}
```

**خطا `422`:**

```json
{ "rooms": [], "error": "invalid_dates" }
```

```json
{ "rooms": [], "error": "range_too_long" }
```

---

### ۵. اتاق‌های فیزیکی کل اقامتگاه

```
GET /api/accommodations/{accommodation_id}/physical-rooms
```

همان پارامترهای بالا. پاسخ:

```json
{
  "rooms": [
    {
      "id": 1,
      "name": "اتاق ۱۰۱",
      "room_type_id": 1,
      "room_type_name": "دو تخته",
      "status": "available",
      "selectable": true,
      ...
    }
  ]
}
```

---

### ۶. تغییر وضعیت علاقه‌مندی

```
POST /favorites/{accommodation_id}/toggle
```

| Middleware | `auth` |
|------------|--------|

**هدرها:**

```http
Accept: application/json
X-CSRF-TOKEN: {token}
```

**پاسخ `200`:**

```json
{ "favorited": true }
```

| مقدار `favorited` | معنی |
|-------------------|------|
| `true` | به علاقه‌مندی‌ها اضافه شد |
| `false` | از علاقه‌مندی‌ها حذف شد |

**بدون احراز هویت:** Redirect `302` → `/login`

---

## Endpointهای Form POST

### ۷. تکمیل پروفایل (اولین ورود)

```
POST /profile/setup
```

| Middleware | `auth` |
|------------|--------|

| فیلد | نوع | الزامی | قوانین |
|------|-----|--------|--------|
| `name` | string | ✅ | ۲–۱۰۰ کاراکتر |
| `national_id` | string | شرطی | ۱۰ رقم — الزامی اگر کاربر هنوز کد ملی ندارد |
| `_token` | string | ✅ | CSRF |

**منطق کد ملی:**
- با `NationalIdVerificationService` اعتبارسنجی می‌شود
- در صورت معتبر: `veteran_type`, `discount_percentage`, `national_id_verified_at` تنظیم می‌شوند
- `secondary_veteran_type` ریست به `null`

**پاسخ موفق:** Redirect `302` → `/`

---

### ۸. تأیید کد ملی (پروفایل)

```
POST /profile/verify-national-id
```

| فیلد | نوع | الزامی |
|------|-----|--------|
| `national_id` | string | ✅ (۱۰ رقم، یکتا) |

**پاسخ موفق:** Redirect back + flash `کد ملی با موفقیت تأیید شد.`

---

### ۹. ثبت رزرو (مسیر اصلی — از صفحه اقامتگاه)

```
POST /accommodations/{accommodation_id}/book
```

| Middleware | `auth` |
|------------|--------|

| فیلد | نوع | الزامی | توضیح |
|------|-----|--------|--------|
| `check_in` | date | ✅ | `>= today` |
| `check_out` | date | ✅ | `> check_in` |
| `guests` | integer | ✅ | ۱ تا `accommodation.capacity` |
| `room_type_id` | integer | ❌ | نوع اتاق |
| `room_rate_id` | integer | ❌ | تعرفه |
| `extra_guests` | integer | ❌ | ۰–۱۰ (کف‌خوابی) |
| `children_under_6` | integer | ❌ | ۰–۱۰ |
| `bill_full_rooms` | boolean | ❌ | رزرو کامل اتاق |

**قیمت‌گذاری:** `BookingPricingService::calculate()` — شامل تخفیف ایثارگری، کودک زیر ۶ سال، نفر اضافه

**بررسی دسترسی:**
- اگر `room_type` مشخص: `RoomType::isAvailable()`
- در غیر این صورت: `Accommodation::isAvailable()`

**پاسخ موفق:** Redirect → `/bookings/{booking_id}`

**وضعیت رزرو:** `confirmed`  
**کد پیگیری:** `tracking_code` (۱۰ کاراکتر uppercase)

**خطاهای رایج:**

| پیام | علت |
|------|-----|
| تاریخ ورود نمی‌تواند در گذشته باشد. | `check_in < today` |
| متأسفانه ظرفیت کافی... وجود ندارد. | اتاق پر |
| تعداد کودک زیر ۶ سال باید کمتر از تعداد کل مهمانان باشد. | `children_under_6 >= guests` |

---

### ۱۰. لغو رزرو (منسوخ — فقط redirect)

```
POST /bookings/{booking_id}/cancel
```

> ⚠️ **Deprecated.** لغو فوری حذف شده. این endpoint فقط به صفحه رزرو با `?cancel=1` redirect می‌کند تا فرم **درخواست کنسلی** باز شود.

---

### ۱۱. ثبت نظر

```
POST /accommodations/{accommodation_id}/reviews
```

| فیلد | نوع | الزامی | قوانین |
|------|-----|--------|--------|
| `rating` | integer | ✅ | ۱–۵ |
| `comment` | string | ❌ | حداکثر ۱۰۰۰ کاراکتر |
| `booking_id` | integer | ❌ | برای redirect به جزئیات رزرو |

**شرط:** کاربر باید رزرو `confirmed` با `check_out < today` داشته باشد.

**منطق:** `Review::updateOrCreate` — یک نظر per کاربر per اقامتگاه

---

### ۱۲. دانلود PDF رسید رزرو

```
GET /bookings/{booking_id}/pdf
```

| Middleware | `auth` |
|------------|--------|

**مجوز:** فقط صاحب رزرو (`booking.user_id === auth.id`)

**پاسخ:** `application/pdf` — فایل `booking-{tracking_code}.pdf`

---

## صفحات و اکشن‌های Livewire

### صفحات (GET)

| مسیر | کامپوننت | Middleware | توضیح |
|------|----------|------------|--------|
| `/` | `Pages\Home` | — | صفحه اصلی |
| `/login` | `Auth\Login` | `guest` | ورود |
| `/login/verify` | `Auth\VerifyOtp` | `guest` | تأیید OTP |
| `/profile/setup` | `Pages\ProfileSetup` | `auth` | تکمیل پروفایل |
| `/profile` | `Pages\ProfileIndex` | `auth` | پروفایل + تاریخچه رزرو |
| `/accommodations` | `Pages\AccommodationIndex` | — | جستجو |
| `/accommodations/{id}` | `Pages\AccommodationShow` | — | جزئیات اقامتگاه |
| `/accommodations/{id}/book` | `Pages\BookingCreate` | `auth` | فرم ساده رزرو |
| `/bookings` | `Pages\BookingIndex` | `auth` | لیست رزروها |
| `/bookings/{id}` | `Pages\BookingShow` | `auth` | جزئیات + کنسلی + نظر |
| `/favorites` | `Pages\FavoriteIndex` | `auth` | علاقه‌مندی‌ها |

### فیلترهای جستجوی اقامتگاه (`/accommodations`)

| Query Param | نوع | توضیح |
|-------------|-----|--------|
| `province_id` | int | فیلتر استان |
| `city_id` | int | فیلتر شهر |
| `check_in` | date | تاریخ ورود |
| `check_out` | date | تاریخ خروج |
| `guests` | int | حداقل ظرفیت |
| `wheelchair` | bool | مناسب ویلچر |
| `type` | string | نوع اقامتگاه (`key` از `/api/v1/accommodation-types`) |
| `lat`, `lng` | float | جستجوی جغرافیایی |
| `radius` | int | شعاع کیلومتر (۱–۵۰۰، پیش‌فرض ۳۰) |

---

### اکشن‌های Livewire (POST /livewire/update)

#### `Auth\Login::sendOtp`

| پراپرتی | نوع | قوانین |
|---------|-----|--------|
| `mobile` | string | `^09[0-9]{9}$` |

معادل `POST /login`

---

#### `Auth\VerifyOtp::verify`

| پراپرتی | نوع |
|---------|-----|
| `otp` | string (۶ رقم) |

معادل `POST /login/verify`

---

#### `Pages\ProfileSetup::save`

معادل `POST /profile/setup`

---

#### `Pages\ProfileIndex::verifyNationalId`

| پراپرتی | نوع |
|---------|-----|
| `nationalId` | string (۱۰ رقم) |

معادل `POST /profile/verify-national-id`

---

#### `Pages\BookingCreate::store`

معادل `POST /accommodations/{id}/book` (با `BookingPricingService`)

---

#### `Pages\AccommodationShow::toggleFavorite`

بدون پارامتر — toggle علاقه‌مندی اقامتگاه فعلی

---

#### `Pages\AccommodationShow::submitReview`

| پراپرتی | نوع |
|---------|-----|
| `rating` | int 1–5 |
| `comment` | string |

---

#### `Pages\BookingShow` — کنسلی و نظر

از trait `ManagesCancellationRequests`:

| متد | دسترسی | توضیح |
|-----|--------|--------|
| `openCancellationRequestModal` | مهمان | باز کردن مودال |
| `closeCancellationRequestModal` | مهمان | بستن مودال |
| `submitCancellationRequest` | مهمان | ثبت درخواست کنسلی |
| `submitReview` | مهمان | ثبت نظر |

**پارامترهای `submitCancellationRequest`:**

| فیلد | نوع | الزامی |
|------|-----|--------|
| `cancellationReasonId` | int/string | ✅ |
| `customReasonText` | string | اگر دلیل «سفارشی» باشد |
| `refundAccountNumber` | string | ✅ (حداکثر ۴۰ کاراکتر) |
| `refundAccountHolderName` | string | ❌ |
| `cancellationNotes` | string | ❌ (حداکثر ۱۰۰۰) |

**پیش‌نمایش بازپرداخت:** `cancellationRefundPreview()` — بر اساس `RefundPolicyService`

```php
// ساختار بازگشتی
[
  'days' => int,        // روز تا ورود (منفی = میان‌اقامت)
  'percentage' => int,  // درصد بازپرداخت
  'amount' => int,      // مبلغ تومان
]
```

**شرایط ثبت کنسلی (`Booking::canRequestCancellation`):**
- `status === 'confirmed'`
- هنوز پنجره کنسلی باز (`check_out` نگذشته)
- درخواست pending دیگری وجود نداشته باشد

---

#### `Pages\FavoriteIndex::toggleFavorite`

| پارامتر | نوع |
|---------|-----|
| `accommodationId` | int |

---

## مینی‌اپ بله

### `GET /miniapp/bale`

صفحه HTML مینی‌اپ

### `POST /miniapp/bale/authenticate`

| فیلد | نوع | الزامی |
|------|-----|--------|
| `init_data` | string | ✅ |
| `phone` | string | ✅ |

**پاسخ موفق `200`:**

```json
{
  "ok": true,
  "redirect": "http://127.0.0.1:8000/"
}
```

**خطا `422`:**

```json
{ "message": "شماره موبایل ارسالی معتبر نیست." }
```

---

## مدل‌های داده

### User (کاربر)

| فیلد | نوع | توضیح |
|------|-----|--------|
| `id` | int | |
| `name` | string\|null | |
| `mobile` | string | یکتا، `09xxxxxxxxx` |
| `national_id` | string\|null | ۱۰ رقم |
| `veteran_type` | string\|null | نوع ایثارگری |
| `secondary_veteran_type` | string\|null | گروه ثانویه |
| `discount_percentage` | int | ۰–۱۰۰ |
| `mobile_verified_at` | datetime\|null | |
| `national_id_verified_at` | datetime\|null | |

**انواع `veteran_type`:**

| مقدار | توضیح |
|-------|--------|
| `martyr_spouse_dependents` | همسر/وابستگان شهید |
| `martyr_children` | فرزندان شهدا |
| `martyr_parents_dependents` | والدین شهدا |
| `veteran_25_49_dependents` | جانباز ۲۵–۴۹٪ |
| `veteran_50_69_dependents` | جانباز ۵۰–۶۹٪ |
| `veteran_70_spouses` | جانباز ۷۰٪+ |
| `freed_prisoner_dependents` | آزادگان |
| `null` | کاربر عادی |

**کد ملی تست (پیشوند):**

| پیشوند | نوع |
|--------|-----|
| `111` | همسر شهید |
| `222` | جانباز ۲۵–۴۹ |
| `333` | جانباز ۵۰–۶۹ |
| `444` | جانباز ۷۰+ |
| `555` | آزادگان |
| سایر | عادی (۰٪) |

---

### Accommodation (اقامتگاه)

| فیلد | نوع | توضیح |
|------|-----|--------|
| `id` | int | |
| `name` | string | |
| `type` | string | کلید نوع — مثلاً `hotel` |
| `type_label` | string | برچسب فارسی نوع — مثلاً «هتل» |
| `description` | string\|null | |
| `price_per_night` | int | ریال |
| `lowest_price` | int | کمترین قیمت بین نرخ‌ها |
| `capacity` | int | ظرفیت کل |
| `rooms` | int | تعداد اتاق |
| `address` | string\|null | |
| `lat`, `lng` | float\|null | |
| `amenities` | string[] | |
| `image` | string\|null | URL تصویر اصلی |
| `images` | string[] | URL تصاویر |
| `average_rating` | float | |
| `review_count` | int | |
| `city` | object | `{ id, name, province: { id, name } }` |
| `is_favorited` | bool | فقط با Bearer Token |
| `user_discount_pct` | int | تخفیف ایثارگری کاربر برای این اقامتگاه |

**انواع `type` (پیش‌فرض):** `hotel`, `villa`, `apartment`, `hostel`, `traditional` — لیست کامل از `GET /api/v1/accommodation-types`

---

### Booking (رزرو)

| فیلد | نوع | توضیح |
|------|-----|--------|
| `id` | int | |
| `user_id` | int | |
| `accommodation_id` | int | |
| `room_type_id` | int\|null | |
| `room_rate_id` | int\|null | |
| `check_in` | date | |
| `check_out` | date | |
| `guests` | int | |
| `children_under_6` | int | |
| `rooms_consumed` | int | |
| `extra_guests` | int | |
| `nights` | int | |
| `base_price` | int | تومان |
| `discount_percentage` | int | |
| `discount_amount` | int | |
| `total_price` | int | |
| `status` | enum | `confirmed`, `cancelled`, `pending`, `completed` |
| `tracking_code` | string | ۱۰ کاراکتر |
| `booking_source` | string | `online` برای کاربر |

---

### CancellationRequest (درخواست کنسلی)

| فیلد | نوع |
|------|-----|
| `status` | `pending` \| `approved` \| `rejected` |
| `refund_amount` | int |
| `refund_percentage` | int |
| `days_before_checkin` | int |
| `refund_account_number` | string |

---

### Review (نظر)

| فیلد | نوع |
|------|-----|
| `rating` | 1–5 |
| `comment` | string\|null |
| `is_visible` | bool |

---

## قوانین کسب‌وکار

### رزرو
1. تاریخ ورود نباید گذشته باشد
2. حداکثر اقامت: **۳۶۵ شب**
3. رزرو آنلاین بلافاصله `confirmed` می‌شود (بدون درگاه پرداخت)
4. قیمت نهایی شامل تخفیف ایثارگری per اقامتگاه است

### نظر
- فقط پس از اتمام اقامت (`check_out < today`)
- یک نظر per کاربر per اقامتگاه

### کنسلی
- جایگزین لغو فوری — نیاز به تأیید ادمین/میزبان
- مبلغ بازپرداخت بر اساس tierهای `RefundPolicyTier` هر اقامتگاه
- تا پایان روز `check_out` قابل ثبت است

### علاقه‌مندی
- Toggle — idempotent
- فقط اقامتگاه‌های `is_active=true` در لیست نمایش داده می‌شوند

---

## کدهای خطا

| HTTP | معنی |
|------|------|
| `200` | موفق (JSON) |
| `302` | Redirect (Form/Livewire) |
| `403` | دسترسی غیرمجاز (مثلاً رزرو دیگران) |
| `404` | یافت نشد |
| `419` | CSRF منقضی/نامعتبر |
| `422` | Validation (JSON APIها) |
| `500` | خطای سرور |

---

## مثال‌های درخواست

### ورود با cURL (Session-based)

```bash
# 1. دریافت CSRF
curl -c cookies.txt -b cookies.txt http://127.0.0.1:8000/login

# 2. استخراج _token از HTML و ارسال OTP
curl -c cookies.txt -b cookies.txt -X POST http://127.0.0.1:8000/login \
  -d "mobile=09120000001&_token=TOKEN"

# 3. تأیید OTP
curl -c cookies.txt -b cookies.txt -X POST http://127.0.0.1:8000/login/verify \
  -d "otp=123456&_token=TOKEN"
```

### Toggle علاقه‌مندی

```bash
curl -b cookies.txt -X POST http://127.0.0.1:8000/favorites/1/toggle \
  -H "Accept: application/json" \
  -H "X-CSRF-TOKEN: TOKEN"
```

### دسترسی تقویم

```bash
curl "http://127.0.0.1:8000/api/room-types/1/availability?months=2026-08,2026-09"
```

### ثبت رزرو

```bash
curl -b cookies.txt -X POST http://127.0.0.1:8000/accommodations/1/book \
  -d "_token=TOKEN" \
  -d "check_in=2026-08-10" \
  -d "check_out=2026-08-13" \
  -d "guests=2" \
  -d "room_type_id=1" \
  -d "room_rate_id=1"
```

---

## تست و دیباگ

### تست‌های خودکار موجود

```bash
php artisan test --filter=ProfileSetup
php artisan test --filter=CancellationRequest
```

| تست | وضعیت |
|-----|--------|
| `ProfileSetupTest` | ✅ |
| `CancellationRequestTest` (۲۴ تست مهمان/کنسلی) | ✅ |

### تست دستی صفحات (کاربر `09120000001`)

| صفحه | URL | وضعیت |
|------|-----|--------|
| خانه | `/` | ✅ |
| رزروهای من | `/bookings` | ✅ |
| پروفایل | `/profile` | ✅ |
| علاقه‌مندی‌ها | `/favorites` | ✅ |
| جستجو | `/accommodations` | ✅ |

---

## ایرادات شناسایی و رفع‌شده

| # | ایراد | وضعیت | توضیح |
|---|-------|--------|--------|
| 1 | `BookingCreate` (Livewire) قیمت ساده محاسبه می‌کرد | ✅ رفع شد | اکنون از `BookingPricingService` استفاده می‌کند (هم‌راستا با `BookingController`) |
| 2 | `ProfileIndex::verifyNationalId` مقدار `secondary_veteran_type` را ریست نمی‌کرد | ✅ رفع شد | هم‌راستا با `ProfileController` |
| 3 | `ProfileSetup::save` همان مشکل ریست | ✅ رفع شد | |
| 4 | `POST /bookings/{id}/cancel` منسوخ اما در routes باقی مانده | ℹ️ مستند شد | فقط redirect به فرم کنسلی |
| 5 | دو مسیر رزرو (`/book` Livewire vs فرم show) | ℹ️ مستند شد | مسیر اصلی: فرم صفحه اقامتگاه → `BookingController@store` |

---

## محدودیت‌ها و نکات توسعه

1. **بدون API Token:** برای اپ موبایل native باید لایه Sanctum/Passport اضافه شود
2. **OTP ثابت `123456`:** فقط محیط توسعه — در production حذف شود
3. **پرداخت آنلاین:** وجود ندارد — رزرو مستقیم `confirmed` می‌شود
4. **Rate Limiting:** روی APIهای عمومی محدودیت صریح تعریف نشده
5. **CORS:** برای APIهای session-based از دامنه دیگر قابل استفاده نیست

---

## فایل‌های مرجع کد

| بخش | فایل |
|-----|------|
| Routes | `routes/web.php` |
| Auth | `app/Http/Controllers/AuthController.php` |
| Profile | `app/Http/Controllers/ProfileController.php` |
| Booking | `app/Http/Controllers/BookingController.php` |
| Favorites | `app/Http/Controllers/FavoriteController.php` |
| Reviews | `app/Http/Controllers/ReviewController.php` |
| Availability | `app/Http/Controllers/AvailabilityController.php` |
| Cities | `app/Http/Controllers/AccommodationController.php` |
| PDF | `app/Http/Controllers/BookingReceiptController.php` |
| Bale | `app/Http/Controllers/BaleMiniAppController.php` |
| Pricing | `app/Services/BookingPricingService.php` |
| Cancellation | `app/Services/CancellationRequestService.php` |
| Refund Policy | `app/Services/RefundPolicyService.php` |
| National ID | `app/Services/NationalIdVerificationService.php` |

---

*این مستند از بررسی کامل کدبیس، تست مرورگر با کاربر `09120000001` / OTP `123456`، و اجرای تست‌های Feature تولید شده است.*
