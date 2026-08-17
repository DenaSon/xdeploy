<?php

declare(strict_types=1);

namespace App\Support\PublicEndpoint;

final class PublicEndpointStatusPresentation
{
    /**
     * @return array{
     *     state:string,
     *     label:string,
     *     description:string,
     *     icon:string,
     *     tone:string,
     *     primary_action:?string,
     *     primary_label:?string,
     *     primary_icon:?string,
     *     footer:?string
     * }
     */
    public static function for(string $state): array
    {
        return match ($state) {
            'enabled' => [
                'state' => 'enabled',
                'label' => 'آماده استفاده',
                'description' => 'دامنه متصل است و HTTPS با موفقیت در دسترس قرار دارد.',
                'icon' => 'lucide.globe-lock',
                'tone' => 'success',
                'primary_action' => 'open',
                'primary_label' => 'باز کردن سرویس',
                'primary_icon' => 'lucide.external-link',
                'footer' => 'DNS و HTTPS فعال هستند و سرویس از طریق این دامنه در دسترس است.',
            ],
            'pending' => [
                'state' => 'pending',
                'label' => 'نیازمند تکمیل',
                'description' => 'دامنه ثبت شده است؛ بررسی DNS و فعال‌سازی HTTPS را تکمیل کنید.',
                'icon' => 'lucide.clock-3',
                'tone' => 'warning',
                'primary_action' => 'manage',
                'primary_label' => 'تکمیل راه‌اندازی',
                'primary_icon' => 'lucide.arrow-left',
                'footer' => 'مرحله بعد: بررسی DNS و فعال‌سازی HTTPS.',
            ],
            'misconfigured' => [
                'state' => 'misconfigured',
                'label' => 'نیازمند بررسی',
                'description' => 'وضعیت ثبت‌شده دامنه با پیکربندی فعلی سرور هم‌خوان نیست.',
                'icon' => 'lucide.triangle-alert',
                'tone' => 'error',
                'primary_action' => 'refresh',
                'primary_label' => 'بررسی وضعیت',
                'primary_icon' => 'lucide.refresh-cw',
                'footer' => 'پیش از تغییر یا حذف دامنه، وضعیت واقعی سرور را دوباره بررسی کنید.',
            ],
            'removing' => [
                'state' => 'removing',
                'label' => 'در حال حذف',
                'description' => 'غیرفعال‌سازی دسترسی عمومی و HTTPS در پس‌زمینه در حال انجام است.',
                'icon' => 'lucide.loader-circle',
                'tone' => 'info',
                'primary_action' => null,
                'primary_label' => null,
                'primary_icon' => null,
                'footer' => 'تا پایان عملیات، تغییر دیگری روی این دامنه انجام ندهید.',
            ],
            'checking' => [
                'state' => 'checking',
                'label' => 'در حال بررسی',
                'description' => 'وضعیت واقعی دامنه و HTTPS از سرور در حال دریافت است.',
                'icon' => 'lucide.loader-circle',
                'tone' => 'info',
                'primary_action' => null,
                'primary_label' => null,
                'primary_icon' => null,
                'footer' => 'پس از پایان بررسی، وضعیت دامنه به‌صورت خودکار به‌روزرسانی می‌شود.',
            ],
            'managed_externally' => [
                'state' => 'managed_externally',
                'label' => 'مدیریت خارجی',
                'description' => 'HTTPS شناسایی شده است اما خارج از Coreflare مدیریت می‌شود.',
                'icon' => 'lucide.external-link',
                'tone' => 'info',
                'primary_action' => 'refresh',
                'primary_label' => 'بررسی مجدد',
                'primary_icon' => 'lucide.refresh-cw',
                'footer' => 'برای اعمال تغییر از Coreflare، ابتدا مالکیت پیکربندی HTTPS را بررسی کنید.',
            ],
            default => [
                'state' => 'unknown',
                'label' => 'وضعیت نامشخص',
                'description' => 'وضعیت واقعی دامنه در حال حاضر قابل تشخیص نیست.',
                'icon' => 'lucide.circle-help',
                'tone' => 'warning',
                'primary_action' => 'refresh',
                'primary_label' => 'بررسی مجدد',
                'primary_icon' => 'lucide.refresh-cw',
                'footer' => 'ارتباط با سرور و وضعیت برنامه دوباره بررسی می‌شود.',
            ],
        };
    }
}
