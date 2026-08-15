import {
    InvalidDomainError,
    NotSupportedError,
    PasskeyExistsError,
    Passkeys,
    UserCancelledError,
} from '@laravel/passkeys';

export const coreflarePasskeys = {
    isSupported() {
        return Passkeys.isSupported();
    },

    async register({ name, optionsUrl, storeUrl }) {
        return Passkeys.register({
            name,
            routes: {
                options: optionsUrl,
                submit: storeUrl,
            },
        });
    },

    messageFor(error) {
        if (error instanceof UserCancelledError) {
            return 'ثبت Passkey لغو شد.';
        }

        if (error instanceof PasskeyExistsError) {
            return 'این Passkey قبلاً برای حساب شما ثبت شده است.';
        }

        if (error instanceof InvalidDomainError) {
            return 'Passkey روی دامنه فعلی قابل استفاده نیست. تنظیمات دامنه برنامه را بررسی کنید.';
        }

        if (error instanceof NotSupportedError) {
            return 'مرورگر یا دستگاه شما از Passkey پشتیبانی نمی‌کند.';
        }

        return 'ثبت Passkey انجام نشد. دوباره تلاش کنید.';
    },
};

window.CoreflarePasskeys = coreflarePasskeys;
window.dispatchEvent(new CustomEvent('passkeys:ready'));
