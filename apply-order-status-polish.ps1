$ErrorActionPreference = 'Stop'

$root = (Get-Location).Path
$bladePath = Join-Path $root 'resources\views\livewire\orders\show.blade.php'
$componentPath = Join-Path $root 'app\Livewire\Orders\Show.php'

if (-not (Test-Path $bladePath)) {
    throw "Order view not found: $bladePath"
}

if (-not (Test-Path $componentPath)) {
    throw "Order component not found: $componentPath"
}

$blade = Get-Content $bladePath -Raw -Encoding UTF8

$blade = $blade.Replace(
    'وضعیت پرداخت و آماده‌سازی VPS را از این صفحه دنبال کن.',
    'وضعیت پرداخت و آماده‌سازی VPS را از این صفحه دنبال کنید.'
)

$blade = $blade.Replace(
    'به‌روزرسانی خودکار',
    'لطفاً صبر کنید'
)

$oldNotice = @'
    @if($paymentNotice)
        <div
            @class([
                '
                    rounded-xl border
                    px-4 py-3
                    text-sm leading-7
                ',
                'border-warning/20 bg-warning/5 text-base-content/70' =>
                    $paymentNotice['type'] === 'warning',
                'border-error/20 bg-error/5 text-error' =>
                    $paymentNotice['type'] === 'error',
                'border-info/20 bg-info/5 text-base-content/70' =>
                    $paymentNotice['type'] === 'info',
            ])
        >
            {{ $paymentNotice['message'] }}
        </div>
    @endif
'@

$newNotice = @'
    @if($paymentNotice)
        <div
            role="alert"
            @class([
                '
                    alert alert-soft
                    rounded-xl
                    text-sm leading-7
                ',
                'alert-warning' =>
                    $paymentNotice['type'] === 'warning',
                'alert-error' =>
                    $paymentNotice['type'] === 'error',
                'alert-info' =>
                    $paymentNotice['type'] === 'info',
            ])
        >
            <x-icon
                :name="match ($paymentNotice['type']) {
                    'error' => 'lucide.circle-alert',
                    'warning' => 'lucide.triangle-alert',
                    default => 'lucide.info',
                }"
                class="!size-4.5 shrink-0"
            />

            <span>
                {{ $paymentNotice['message'] }}
            </span>
        </div>
    @endif
'@

if (-not $blade.Contains($oldNotice)) {
    throw 'Expected payment notice block was not found. The order view may have changed.'
}

$blade = $blade.Replace($oldNotice, $newNotice)
Set-Content $bladePath $blade -Encoding UTF8

$component = Get-Content $componentPath -Raw -Encoding UTF8

$copyReplacements = @{
    'چند لحظه صبر کنید و دوباره تلاش کنید.' =
        'لطفاً چند لحظه صبر کنید و سپس دوباره تلاش کنید.'

    'سفارش حفظ شده است. چند لحظه دیگر دوباره تلاش کنید.' =
        'سفارش شما حفظ شده است. لطفاً چند لحظه دیگر دوباره تلاش کنید.'

    'سرور ساخته شده و اتصال xDeploy نیز آماده است.' =
        'سرور با موفقیت ساخته شده و اتصال xDeploy نیز آماده استفاده است.'

    'ساخت سرور در Cloud کامل شده و بررسی اتصال xDeploy در حال انجام است.' =
        'ساخت VPS با موفقیت تکمیل شده است. در حال بررسی آمادگی اتصال xDeploy هستیم.'

    'سفارش ثبت شده و برای ادامه باید پرداخت انجام شود.' =
        'سفارش ثبت شده است. برای ادامه فرایند، لطفاً پرداخت را تکمیل کنید.'

    'پرداخت تأیید شده و سفارش در صف ساخت سرور قرار گرفته است.' =
        'پرداخت با موفقیت تأیید شده است و سفارش برای ساخت VPS در صف قرار دارد.'

    'درخواست ساخت به Cloud Provider ارسال شده است.' =
        'درخواست ساخت VPS به ارائه‌دهنده زیرساخت ارسال شده است.'

    'ساخت خودکار متوقف شده است. برای جلوگیری از ایجاد سرور تکراری، تلاش مجدد خودکار انجام نمی‌شود.' =
        'فرایند ساخت متوقف شده است. برای جلوگیری از ایجاد VPS تکراری، تلاش مجدد به‌صورت خودکار انجام نمی‌شود.'

    'این سفارش دیگر وارد فرایند ساخت سرور نمی‌شود.' =
        'این سفارش لغو شده است و وارد فرایند ساخت VPS نخواهد شد.'

    'اعتبار قیمت این سفارش تمام شده است. یک سفارش جدید ایجاد کنید.' =
        'اعتبار قیمت این سفارش به پایان رسیده است. لطفاً یک سفارش جدید ایجاد کنید.'

    'پرداخت قبلی لغو شد. اگر پیش‌فاکتور هنوز معتبر باشد می‌توانید دوباره پرداخت را شروع کنید.' =
        'پرداخت قبلی لغو شده است. در صورت معتبر بودن پیش‌فاکتور، می‌توانید پرداخت را دوباره آغاز کنید.'

    'شروع پرداخت قبلی ناموفق بود. سفارش شما حفظ شده و می‌توانید دوباره تلاش کنید.' =
        'شروع پرداخت قبلی انجام نشد. سفارش شما حفظ شده است و می‌توانید دوباره تلاش کنید.'

    'درخواست پرداخت در حال آماده‌سازی است. از ایجاد چند پرداخت هم‌زمان جلوگیری می‌شود.' =
        'درخواست پرداخت در حال آماده‌سازی است. لطفاً صبر کنید؛ از ایجاد پرداخت‌های هم‌زمان جلوگیری می‌شود.'

    'یک پرداخت فعال برای این سفارش وجود دارد. دکمه پرداخت شما را به همان درخواست برمی‌گرداند.' =
        'یک پرداخت فعال برای این سفارش وجود دارد. با انتخاب ادامه پرداخت، همان درخواست پرداخت ادامه خواهد یافت.'
}

foreach ($entry in $copyReplacements.GetEnumerator()) {
    $component = $component.Replace($entry.Key, $entry.Value)
}

Set-Content $componentPath $component -Encoding UTF8

Write-Host 'Order status UI copy and alert styling updated successfully.'
