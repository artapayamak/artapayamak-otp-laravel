# ارسال رمز یک‌بارمصرف (OTP) با Laravel و آرتا پیامک

این مخزن نمونه‌ای ساده و کاربردی برای ارسال رمز یک‌بارمصرف یا **OTP** در Laravel با استفاده از API سرویس [آرتا پیامک](https://artapayamak.com/) ارائه می‌کند.

در این نمونه، درخواست ارسال پیامک با استفاده از **HTTP Client داخلی Laravel** به API ارسال می‌شود. اطلاعات حساس مانند توکن API نیز از متغیرهای محیطی خوانده می‌شوند و داخل کد قرار نمی‌گیرند.

از این نمونه می‌توانید برای موارد زیر استفاده کنید:

- ورود کاربران با شماره موبایل
- ثبت‌نام و تأیید شماره موبایل
- بازیابی رمز عبور
- تأیید عملیات حساس
- احراز هویت دومرحله‌ای
- ارسال کد تأیید کوتاه‌مدت

برای آشنایی بیشتر با سرویس OTP می‌توانید صفحه [پیامک OTP](https://artapayamak.com/otp-sms/) و مستندات [API ارسال پیامک OTP](https://artapayamak.com/sms-otp-api/) را مشاهده کنید.

---

## ویژگی‌های نمونه

- استفاده از HTTP Client داخلی Laravel
- ارسال پیامک بر اساس الگوی تأییدشده
- دریافت توکن API از فایل `.env`
- استفاده از `config/services.php` برای مدیریت تنظیمات API
- تعیین محدودیت زمانی برای درخواست
- مدیریت خطاهای HTTP
- جداسازی منطق ارسال پیامک از Controller
- مناسب برای ورود، ثبت‌نام و تأیید شماره موبایل
- بدون استفاده از `SOAP`

---

## پیش‌نیازها

برای استفاده از این نمونه به موارد زیر نیاز دارید:

- PHP سازگار با نسخه Laravel پروژه
- یک پروژه Laravel
- Composer
- حساب فعال در [آرتا پیامک](https://artapayamak.com/)
- توکن معتبر API
- شماره فرستنده مجاز
- الگوی پیامک تأییدشده در پنل

این نمونه از HTTP Client داخلی Laravel استفاده می‌کند؛ بنابراین در یک پروژه استاندارد Laravel معمولاً نیازی به نصب پکیج جداگانه‌ای برای ارسال درخواست HTTP ندارید.

---

## دریافت اطلاعات موردنیاز

پیش از استفاده از نمونه، اطلاعات زیر را از پنل خود دریافت کنید:

1. توکن API
2. شماره فرستنده
3. شناسه الگوی پیامک
4. نام پارامترهای تعریف‌شده در الگو

در نمونه حاضر:

```text
from_number = +983000505
```

و شناسه الگو به‌صورت مقدار نمونه زیر نمایش داده شده است:

```text
xxxxxxxxxxxxxxx
```

مقدار `xxxxxxxxxxxxxxx` را با شناسه واقعی الگوی تأییدشده در پنل خود جایگزین کنید.

---

## محل نمونه کد در این مخزن

فایل Service در این مخزن در مسیر زیر قرار دارد:

```text
app/Services/SendOtpService.php
```

این مسیر فقط محل فایل نمونه در همین مخزن است و استفاده از آن برای پروژه‌های دیگر الزامی نیست. می‌توانید کلاس ارسال OTP را متناسب با معماری، Namespace و ساختار پروژه خود در مسیر دیگری قرار دهید.

اگر محل کلاس را تغییر دادید، مقدار `namespace` و دستور `use` مربوط به کلاس را نیز متناسب با ساختار پروژه خود تنظیم کنید.

---

## تنظیم توکن API

توکن API را در فایل `.env` پروژه Laravel قرار دهید:

```env
IPPANEL_API_TOKEN=your_api_token_here
```

مقدار `your_api_token_here` را با توکن واقعی API جایگزین کنید.

توکن API نباید مستقیماً داخل Service، Controller یا سایر فایل‌های PHP نوشته شود.

### تنظیم `config/services.php`

تنظیم زیر را به آرایه موجود در فایل `config/services.php` اضافه کنید:

```php
'ippanel' => [
    'token' => env('IPPANEL_API_TOKEN'),
],
```

پس از این تنظیم، توکن در بخش‌های مختلف برنامه از طریق دستور زیر قابل دریافت است:

```php
config('services.ippanel.token')
```

استفاده مستقیم از `env()` خارج از فایل‌های تنظیمات Laravel توصیه نمی‌شود. خواندن توکن از `config()` با سیستم کش تنظیمات Laravel سازگاری بهتری دارد.

اگر تنظیمات پروژه قبلاً کش شده است، پس از تغییر فایل `.env` یا `config/services.php` دستور زیر را اجرا کنید:

```bash
php artisan config:clear
```

در محیط Production می‌توانید پس از اطمینان از صحت تنظیمات، کش را دوباره ایجاد کنید:

```bash
php artisan config:cache
```

---

## نمونه کامل Service

محتوای فایل `app/Services/SendOtpService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SendOtpService
{
    public function send(string $mobile, string $otp): array
    {
        $apiToken = config('services.ippanel.token');

        if (empty($apiToken)) {
            throw new RuntimeException(
                'IPPANEL_API_TOKEN is not configured.'
            );
        }

        $response = Http::withHeaders([
            'Authorization' => $apiToken,
        ])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(15)
            ->post('https://edge.ippanel.com/v1/api/send', [
                'sending_type' => 'pattern',
                'from_number' => '+983000505',
                'code' => 'xxxxxxxxxxxxxxx',
                'recipients' => [$mobile],
                'params' => [
                    'code' => $otp,
                ],
            ]);

        $response->throw();

        return $response->json();
    }
}
```

مقدار زیر را با شناسه واقعی الگوی پیامک خود جایگزین کنید:

```php
'code' => 'xxxxxxxxxxxxxxx',
```

---

## توضیح خط‌به‌خط کد

### فعال‌کردن بررسی دقیق نوع داده‌ها

```php
declare(strict_types=1);
```

این دستور بررسی نوع داده‌ها را در فایل فعلی سخت‌گیرانه‌تر می‌کند. در نتیجه، استفاده از Type Hintها رفتار قابل پیش‌بینی‌تری خواهد داشت.

---

### تعریف Namespace

```php
namespace App\Services;
```

این خط Namespace کلاس را مشخص می‌کند. در این مخزن، کلاس داخل فضای نام `App\Services` قرار گرفته است.

اگر فایل را به مسیر یا فضای نام دیگری منتقل کردید، این مقدار را نیز متناسب با ساختار پروژه تغییر دهید.

---

### واردکردن کلاس‌های موردنیاز

```php
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
```

کاربرد کلاس‌ها:

- `Http` برای ارسال درخواست HTTP با HTTP Client داخلی Laravel استفاده می‌شود.
- `RuntimeException` برای اعلام نبودن توکن API در تنظیمات استفاده می‌شود.
- `Response` نوع پاسخ HTTP Client را مشخص می‌کند و در صورت توسعه متدهای دیگر Service قابل استفاده است.

اگر از `Response` به‌صورت مستقیم در نسخه نهایی کد استفاده نمی‌کنید، می‌توانید Import آن را حذف کنید:

```php
use Illuminate\Http\Client\Response;
```

---

### تعریف کلاس Service

```php
class SendOtpService
```

این کلاس مسئول ارسال OTP است. قرار دادن منطق ارسال پیامک در یک Service باعث می‌شود Controllerها ساده‌تر بمانند و کد در بخش‌های مختلف برنامه قابل استفاده مجدد باشد.

---

### تعریف متد ارسال OTP

```php
public function send(string $mobile, string $otp): array
```

این متد دو ورودی دریافت می‌کند:

- `$mobile`: شماره موبایل گیرنده
- `$otp`: رمز یک‌بارمصرفی که باید برای کاربر ارسال شود

خروجی متد از نوع `array` است و پاسخ JSON دریافت‌شده از API را به آرایه PHP تبدیل می‌کند.

---

### خواندن توکن از تنظیمات Laravel

```php
$apiToken = config('services.ippanel.token');
```

این خط توکن را از تنظیمی که در `config/services.php` تعریف شده است دریافت می‌کند.

مقدار اصلی توکن همچنان در فایل `.env` نگهداری می‌شود، اما Service مستقیماً `env()` را فراخوانی نمی‌کند.

---

### بررسی وجود توکن

```php
if (empty($apiToken)) {
    throw new RuntimeException(
        'IPPANEL_API_TOKEN is not configured.'
    );
}
```

اگر توکن تعریف نشده یا مقدار آن خالی باشد، اجرای درخواست متوقف و یک Exception ایجاد می‌شود.

این بررسی از ارسال درخواست بدون هدر احراز هویت جلوگیری می‌کند و تشخیص خطای تنظیمات را آسان‌تر می‌سازد.

---

### تعریف هدر احراز هویت

```php
Http::withHeaders([
    'Authorization' => $apiToken,
])
```

توکن API در هدر `Authorization` قرار می‌گیرد و برای احراز هویت درخواست استفاده می‌شود.

توکن نباید داخل کد Hardcode شود.

> اگر مستندات نسخه API مورد استفاده شما قالب دیگری مانند `Bearer` را برای هدر احراز هویت مشخص کرده است، هدر را دقیقاً مطابق همان مستندات تنظیم کنید.

---

### درخواست پاسخ JSON

```php
->acceptJson()
```

این متد مشخص می‌کند که برنامه انتظار دارد پاسخ API با فرمت JSON دریافت شود.

---

### ارسال داده‌ها با فرمت JSON

```php
->asJson()
```

این متد به HTTP Client اعلام می‌کند که Payload درخواست با فرمت JSON ارسال شود.

---

### تعیین زمان اتصال

```php
->connectTimeout(10)
```

این متد حداکثر زمان انتظار برای برقراری اتصال را روی ۱۰ ثانیه تنظیم می‌کند.

---

### تعیین زمان کل درخواست

```php
->timeout(15)
```

این متد حداکثر زمان اجرای درخواست را روی ۱۵ ثانیه تنظیم می‌کند. استفاده از Timeout مانع از انتظار نامحدود برنامه هنگام اختلال شبکه یا در دسترس نبودن API می‌شود.

---

### آدرس API

```php
->post('https://edge.ippanel.com/v1/api/send', [
```

درخواست با متد `POST` به Endpoint ارسال پیامک فرستاده می‌شود:

```text
https://edge.ippanel.com/v1/api/send
```

---

### تعیین نوع ارسال

```php
'sending_type' => 'pattern',
```

مقدار `pattern` مشخص می‌کند که پیامک با استفاده از الگوی از پیش تعریف‌شده و تأییدشده ارسال می‌شود.

این روش برای ارسال سریع پیامک‌های OTP مناسب است.

---

### تعیین شماره فرستنده

```php
'from_number' => '+983000505',
```

این مقدار شماره فرستنده پیامک را مشخص می‌کند.

در صورت نیاز، آن را با شماره فرستنده مجاز حساب خود جایگزین کنید.

---

### تعیین شناسه الگو

```php
'code' => 'xxxxxxxxxxxxxxx',
```

این `code` شناسه الگوی پیامک در پنل است.

مقدار `xxxxxxxxxxxxxxx` فقط یک مقدار نمونه است و باید با شناسه واقعی الگوی تأییدشده جایگزین شود.

---

### تعیین گیرنده

```php
'recipients' => [$mobile],
```

شماره موبایل گیرنده در قالب آرایه برای API ارسال می‌شود.

حتی هنگام ارسال پیامک برای یک شماره، مقدار `recipients` به‌صورت آرایه تعریف می‌شود.

---

### ارسال مقدار OTP به الگو

```php
'params' => [
    'code' => $otp,
],
```

در این بخش، مقدار OTP به پارامتری با نام `code` در الگوی پیامک ارسال می‌شود.

نام این پارامتر باید دقیقاً با نام متغیر تعریف‌شده در الگوی پنل مطابقت داشته باشد.

---

## تفاوت دو مقدار `code`

در Payload این نمونه دو کلید با نام `code` وجود دارد، اما کاربرد آن‌ها متفاوت است.

### شناسه الگوی پیامک

```php
'code' => 'xxxxxxxxxxxxxxx',
```

این مقدار در سطح اصلی Payload قرار دارد و شناسه الگوی تأییدشده در پنل است.

### مقدار رمز یک‌بارمصرف

```php
'params' => [
    'code' => $otp,
],
```

این مقدار داخل `params` قرار دارد و همان OTP است که در متن پیامک جایگزین می‌شود.

بنابراین:

- `payload.code`: شناسه الگوی پیامک
- `payload.params.code`: مقدار OTP ارسالی به کاربر

این دو مقدار نباید با یکدیگر اشتباه گرفته شوند.

---

## مدیریت خطاهای HTTP

```php
$response->throw();
```

متد `throw()` پاسخ‌های ناموفق HTTP را به Exception تبدیل می‌کند.

برای مثال، پاسخ‌های زیر می‌توانند باعث ایجاد Exception شوند:

- `401 Unauthorized`
- `403 Forbidden`
- `404 Not Found`
- `422 Unprocessable Entity`
- `429 Too Many Requests`
- خطاهای سری `500`

بدون استفاده از `throw()` ممکن است برنامه یک پاسخ خطا را مانند پاسخ موفق پردازش کند.

---

### تبدیل پاسخ JSON به آرایه

```php
return $response->json();
```

این خط بدنه JSON پاسخ API را به آرایه PHP تبدیل و بازمی‌گرداند.

---

## نمونه استفاده در Controller

کلاس Service را می‌توانید با Dependency Injection در Controller دریافت کنید:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SendOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class OtpController extends Controller
{
    public function send(
        Request $request,
        SendOtpService $sendOtpService
    ): JsonResponse {
        $validated = $request->validate([
            'mobile' => [
                'required',
                'string',
                'regex:/^09\d{9}$/',
            ],
        ]);

        $otp = (string) random_int(100000, 999999);

        try {
            $result = $sendOtpService->send(
                $validated['mobile'],
                $otp
            );

            return response()->json([
                'message' => 'رمز یک‌بارمصرف ارسال شد.',
                'result' => $result,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'ارسال رمز یک‌بارمصرف انجام نشد.',
            ], 500);
        }
    }
}
```

این کد فقط یک نمونه ساده برای نمایش نحوه فراخوانی Service است. در یک سامانه واقعی باید OTP را به‌صورت امن ذخیره و محدودیت‌های امنیتی لازم را نیز اعمال کنید.

---

## تولید امن OTP

برای تولید OTP عددی شش‌رقمی می‌توانید از `random_int()` استفاده کنید:

```php
$otp = (string) random_int(100000, 999999);
```

استفاده از `random_int()` نسبت به روش‌هایی مانند `rand()` برای تولید مقادیر امنیتی انتخاب مناسب‌تری است.

OTP تولیدشده را قبل از ارسال، همراه با زمان انقضا و اطلاعات لازم برای اعتبارسنجی ذخیره کنید.

---

## اعتبارسنجی شماره موبایل

در نمونه Controller از قانون زیر برای شماره‌های موبایل ایران استفاده شده است:

```php
'regex:/^09\d{9}$/'
```

این الگو شماره‌ای با فرمت زیر را می‌پذیرد:

```text
09123456789
```

اگر API یا برنامه شما شماره را با فرمت بین‌المللی دریافت می‌کند، باید اعتبارسنجی و تبدیل شماره را متناسب با فرمت مورد انتظار انجام دهید.

برای مثال:

```text
+989123456789
```

اعتبارسنجی Regex فقط شکل اولیه شماره را بررسی می‌کند و مالکیت واقعی آن را تأیید نمی‌کند. مالکیت شماره پس از واردکردن صحیح OTP تأیید می‌شود.

---

## ذخیره و بررسی OTP

ارسال پیامک فقط بخشی از فرایند احراز هویت است. برنامه باید OTP را برای مدت کوتاهی ذخیره و هنگام دریافت کد از کاربر، آن را بررسی کند.

برای ذخیره OTP می‌توانید از موارد زیر استفاده کنید:

- Cache داخلی Laravel
- Redis
- Database
- سرویس ذخیره‌سازی کوتاه‌مدت امن

نمونه ساده با Cache:

```php
use Illuminate\Support\Facades\Cache;

$otp = (string) random_int(100000, 999999);

Cache::put(
    'otp:' . $mobile,
    hash('sha256', $otp),
    now()->addMinutes(2)
);

$sendOtpService->send($mobile, $otp);
```

برای بررسی OTP:

```php
$storedOtp = Cache::get('otp:' . $mobile);
$submittedOtp = (string) $request->input('otp');

$isValid = $storedOtp !== null
    && hash_equals(
        $storedOtp,
        hash('sha256', $submittedOtp)
    );
```

بعد از تأیید موفق، OTP را حذف کنید:

```php
Cache::forget('otp:' . $mobile);
```

این کد صرفاً نمونه است. طراحی نهایی باید با معماری احراز هویت، تعداد کاربران و سیاست‌های امنیتی پروژه هماهنگ شود.

---

## نکات امنیتی مهم

### توکن API را داخل کد قرار ندهید

روش ناامن:

```php
'Authorization' => 'real-api-token',
```

روش مناسب:

```php
'Authorization' => config('services.ippanel.token'),
```

توکن واقعی باید فقط در فایل `.env` یا Secret Manager محیط اجرا قرار گیرد.

---

### فایل `.env` را در Git ثبت نکنید

در پروژه‌های Laravel فایل `.env` معمولاً از قبل داخل `.gitignore` قرار دارد. بااین‌حال، از وجود تنظیم زیر مطمئن شوید:

```gitignore
.env
.env.*
!.env.example
```

فایل `.env.example` می‌تواند در مخزن قرار گیرد، اما نباید حاوی توکن واقعی باشد:

```env
IPPANEL_API_TOKEN=your_api_token_here
```

اگر توکن واقعی به‌اشتباه در Git ثبت شد، حذف آن از فایل کافی نیست. توکن را فوراً از پنل باطل یا تعویض کنید و در صورت نیاز تاریخچه Git را نیز پاک‌سازی کنید.

---

### OTP را در Log ثبت نکنید

از ثبت موارد زیر در Log خودداری کنید:

- مقدار OTP
- توکن API
- هدر `Authorization`
- پاسخ‌هایی که اطلاعات حساس دارند
- اطلاعات کامل درخواست احراز هویت

روش نامناسب:

```php
logger()->info('OTP generated', [
    'mobile' => $mobile,
    'otp' => $otp,
]);
```

در صورت نیاز به ثبت رویداد، فقط اطلاعات غیرحساس ثبت شود:

```php
logger()->info('OTP send requested');
```

---

### برای OTP زمان انقضا تعریف کنید

OTP باید کوتاه‌مدت باشد. معمولاً بازه‌ای بین ۲ تا ۵ دقیقه، بسته به سیاست امنیتی برنامه، مناسب است.

کد منقضی‌شده نباید قابل استفاده باشد.

---

### OTP باید یک‌بارمصرف باشد

پس از تأیید موفق، OTP را فوراً حذف یا باطل کنید. استفاده مجدد از یک کد تأییدشده نباید امکان‌پذیر باشد.

---

### تعداد تلاش‌ها را محدود کنید

برای واردکردن OTP محدودیت تعیین کنید. برای مثال، پس از چند تلاش ناموفق:

- OTP را باطل کنید.
- تأیید را موقتاً مسدود کنید.
- از کاربر بخواهید OTP جدید دریافت کند.

---

### ارسال مجدد را محدود کنید

برای جلوگیری از سوءاستفاده، ارسال OTP را بر اساس موارد زیر محدود کنید:

- شماره موبایل
- آدرس IP
- شناسه کاربر یا Session
- Device Fingerprint، در صورت نیاز
- بازه زمانی مشخص

در Laravel می‌توانید برای Endpoint ارسال OTP از Rate Limiting Middleware استفاده کنید.

---

### پاسخ‌های API را مستقیم به کاربر نمایش ندهید

جزئیات Exception یا پاسخ کامل API ممکن است حاوی اطلاعات فنی یا حساس باشد.

در محیط Production، پیام عمومی به کاربر نمایش دهید و جزئیات فنی را فقط به سامانه مانیتورینگ امن ارسال کنید.

---

### شماره موبایل را اعتبارسنجی و نرمال‌سازی کنید

قبل از ارسال درخواست:

- فرمت شماره را بررسی کنید.
- فاصله و نویسه‌های اضافی را حذف کنید.
- شماره را به یک فرمت ثابت تبدیل کنید.
- از ارسال OTP به ورودی‌های نامعتبر جلوگیری کنید.

---

### از HTTPS استفاده کنید

Endpoint این نمونه از HTTPS استفاده می‌کند:

```text
https://edge.ippanel.com/v1/api/send
```

در محیط Production، فرم‌ها و Endpointهای پروژه خود را نیز فقط از طریق HTTPS ارائه دهید.

---

## مدیریت Exception در Laravel

برای مدیریت دقیق‌تر خطاهای HTTP می‌توانید از `RequestException` استفاده کنید:

```php
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

try {
    $result = $sendOtpService->send($mobile, $otp);
} catch (ConnectionException $exception) {
    report($exception);

    return response()->json([
        'message' => 'ارتباط با سرویس پیامک برقرار نشد.',
    ], 503);
} catch (RequestException $exception) {
    report($exception);

    return response()->json([
        'message' => 'سرویس پیامک درخواست را نپذیرفت.',
    ], 502);
}
```

- `ConnectionException` معمولاً هنگام Timeout یا برقرار نشدن اتصال ایجاد می‌شود.
- `RequestException` پس از اجرای `throw()` برای پاسخ‌های HTTP ناموفق ایجاد می‌شود.

جزئیات کامل Exception را در پاسخ عمومی API نمایش ندهید.

---

## خطاهای رایج

### خطای `401 Unauthorized`

دلایل احتمالی:

- توکن API تعریف نشده است.
- توکن اشتباه یا منقضی شده است.
- مقدار `.env` بعد از تغییر، در تنظیمات Laravel اعمال نشده است.
- قالب هدر `Authorization` با مستندات API مطابقت ندارد.

راهکار:

```bash
php artisan config:clear
```

سپس مقدار زیر را بررسی کنید:

```env
IPPANEL_API_TOKEN=your_real_api_token
```

---

### خطای نبودن توکن در تنظیمات

اگر خطای زیر را دریافت کردید:

```text
IPPANEL_API_TOKEN is not configured.
```

موارد زیر را بررسی کنید:

1. متغیر `IPPANEL_API_TOKEN` در فایل `.env` وجود داشته باشد.
2. تنظیم `ippanel` در `config/services.php` اضافه شده باشد.
3. کش تنظیمات پاک یا دوباره ایجاد شده باشد.
4. نام متغیر در `.env` و فایل تنظیمات دقیقاً یکسان باشد.

---

### خطای الگوی نامعتبر

دلایل احتمالی:

- مقدار `code` اصلی اشتباه است.
- الگو هنوز تأیید نشده است.
- الگو متعلق به حساب یا توکن دیگری است.
- نام پارامترهای `params` با متغیرهای الگو مطابقت ندارد.

توجه کنید:

```php
'code' => 'xxxxxxxxxxxxxxx',
```

شناسه الگو است، اما:

```php
'params' => [
    'code' => $otp,
],
```

مقدار OTP است.

---

### پیامک ارسال می‌شود اما OTP داخل آن قرار نمی‌گیرد

نام کلید داخل `params` باید دقیقاً با نام متغیر تعریف‌شده در الگو برابر باشد.

اگر نام متغیر الگو `verification-code` یا نام دیگری است، Payload را نیز مطابق همان نام تنظیم کنید.

---

### خطای شماره گیرنده

موارد زیر را بررسی کنید:

- شماره موبایل فرمت معتبر داشته باشد.
- شماره شامل فاصله یا نویسه اضافی نباشد.
- فرمت شماره با فرمت مورد قبول API هماهنگ باشد.
- مقدار `recipients` به‌صورت آرایه ارسال شود.

نمونه:

```php
'recipients' => [$mobile],
```

---

### خطای شماره فرستنده

مقدار زیر باید یک شماره فرستنده معتبر و مجاز برای حساب شما باشد:

```php
'from_number' => '+983000505',
```

در صورت تفاوت شماره فرستنده حساب، مقدار آن را تغییر دهید.

---

### خطای Timeout یا اتصال

دلایل احتمالی:

- اختلال شبکه
- در دسترس نبودن موقت API
- محدودیت Firewall
- مشکل DNS
- کوتاه بودن Timeout
- محدودیت ارتباط خروجی روی سرور

تنظیم‌های مربوط به Timeout:

```php
->connectTimeout(10)
->timeout(15)
```

در صورت تلاش مجدد، از Retry کنترل‌شده استفاده کنید تا یک OTP چند بار برای کاربر ارسال نشود.

---

### تغییرات `.env` اعمال نمی‌شوند

کش تنظیمات را پاک کنید:

```bash
php artisan config:clear
```

برای بررسی محیط توسعه می‌توانید به‌صورت موقت مقدار تنظیم را بررسی کنید، اما توکن را در Log یا خروجی عمومی نمایش ندهید.

برای نمونه، فقط وجود مقدار را بررسی کنید:

```php
config('services.ippanel.token') !== null;
```

---

## آزمایش ارسال بدون ارسال پیامک واقعی

Laravel امکان Fake کردن HTTP Client را فراهم می‌کند. با استفاده از `Http::fake()` می‌توانید Service را بدون ارسال درخواست واقعی آزمایش کنید.

```php
<?php

use App\Services\SendOtpService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

test('it sends an OTP request', function () {
    Config::set('services.ippanel.token', 'test-token');

    Http::fake([
        'https://edge.ippanel.com/v1/api/send' => Http::response([
            'status' => 'success',
        ], 200),
    ]);

    $service = new SendOtpService();

    $result = $service->send(
        '09123456789',
        '123456'
    );

    Http::assertSent(function ($request) {
        return $request->url()
            === 'https://edge.ippanel.com/v1/api/send'
            && $request->hasHeader(
                'Authorization',
                'test-token'
            )
            && $request['sending_type'] === 'pattern'
            && $request['recipients'] === ['09123456789']
            && $request['params']['code'] === '123456';
    });

    expect($result)->toBe([
        'status' => 'success',
    ]);
});
```

در این تست:

- هیچ پیامک واقعی ارسال نمی‌شود.
- وجود هدر `Authorization` بررسی می‌شود.
- آدرس API بررسی می‌شود.
- شماره گیرنده بررسی می‌شود.
- مقدار OTP بررسی می‌شود.
- پاسخ فرضی API آزمایش می‌شود.

در تست‌های خود از توکن واقعی و شماره کاربران واقعی استفاده نکنید.

---

## نمونه اصلی ساده

نمونه اولیه این مخزن به شکل زیر است:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SendOtpService
{
    public function send($mobile, $otp)
    {
        $response = Http::withHeaders([
            'Authorization' => env('IPPANEL_API_TOKEN'),
        ])->post('https://edge.ippanel.com/v1/api/send', [
            'sending_type' => 'pattern',
            'from_number' => '+983000505',
            'code' => 'xxxxxxxxxxxxxxx',
            'recipients' => [$mobile],
            'params' => [
                'code' => $otp,
            ],
        ]);

        return $response->json();
    }
}
```

این نسخه برای نمایش ساده نحوه ارسال درخواست مناسب است. بااین‌حال، برای استفاده در پروژه واقعی توصیه می‌شود:

- به‌جای `env()` مستقیم از `config()` استفاده کنید.
- برای درخواست Timeout تعیین کنید.
- خطاهای HTTP را با `throw()` مدیریت کنید.
- نوع ورودی‌ها و خروجی متد را مشخص کنید.
- وجود توکن API را پیش از ارسال درخواست بررسی کنید.
- خطاها را بدون افشای اطلاعات حساس مدیریت کنید.

---

## توصیه‌های محیط Production

پیش از استفاده در محیط عملیاتی، موارد زیر را در نظر بگیرید:

- توکن API را در Secret Manager یا متغیرهای محیطی امن نگهداری کنید.
- دسترسی به توکن را محدود کنید.
- OTP را به‌صورت Hash ذخیره کنید.
- برای OTP زمان انقضای کوتاه تعریف کنید.
- تعداد تلاش‌های تأیید را محدود کنید.
- ارسال مجدد را Rate Limit کنید.
- OTP را پس از استفاده موفق حذف کنید.
- از ثبت OTP و توکن در Log جلوگیری کنید.
- خطاهای API را مانیتور کنید.
- برای اختلال‌های موقت Retry محدود و کنترل‌شده در نظر بگیرید.
- درخواست‌های مشکوک را شناسایی و مسدود کنید.
- شماره موبایل را قبل از ارسال نرمال‌سازی کنید.

---

## لینک‌های مرتبط

- [آرتا پیامک](https://artapayamak.com/)
- [پیامک OTP](https://artapayamak.com/otp-sms/)
- [API ارسال پیامک OTP](https://artapayamak.com/sms-otp-api/)
- [ارسال پیامک در n8n](https://artapayamak.com/send-sms-n8n/)

---

## نکته پایانی

این مخزن یک نمونه آموزشی برای ارسال OTP با Laravel و API آرتا پیامک است. مسیر فایل Service، نحوه استفاده از Controller و تنظیمات نمایش‌داده‌شده را می‌توانید متناسب با معماری پروژه خود تغییر دهید.

مهم‌ترین موارد برای استفاده صحیح و امن عبارت‌اند از:

- نگهداری توکن خارج از کد
- استفاده از `config()` برای خواندن تنظیمات در Laravel
- جایگزین‌کردن شناسه واقعی الگو
- مدیریت خطاهای HTTP
- تعیین Timeout
- تولید امن OTP
- ذخیره کوتاه مدت OTP
- محدودکردن ارسال و تعداد تلاش‌ها
- حذف OTP پس از تأیید موفق
- جلوگیری از ثبت اطلاعات حساس در Git و Log
