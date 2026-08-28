<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use phpseclib3\Crypt\PublicKeyLoader;
use Throwable;

final readonly class SSHPrivateKey implements ValidationRule
{
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail,
    ): void {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        try {
            PublicKeyLoader::loadPrivateKey(
                $value,
            );
        } catch (Throwable) {
            $fail(
                'کلید خصوصی SSH معتبر نیست. Private Key بدون passphrase را وارد کنید؛ فایل .pub قابل استفاده نیست.',
            );
        }
    }
}
