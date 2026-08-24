<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

final class AnalyticsPropertySanitizer
{
    /** @var list<string> */
    private const array SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'credential',
        'secret',
        'token',
        'otp',
        'phone',
        'authorization',
        'merchant',
        'gateway_reference',
        'gateway_transaction_id',
        'api_key',
        'access_key',
        'private_key',
    ];

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public function sanitize(array $properties): array
    {
        $sanitized = [];

        foreach ($properties as $key => $value) {
            $normalizedKey = strtolower(trim((string) $key));

            if ($normalizedKey === '' || $this->isSensitiveKey($normalizedKey)) {
                continue;
            }

            $sanitized[$key] = is_array($value)
                ? $this->sanitize($value)
                : $this->normalizeScalar($value);
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        if ($key === 'code') {
            return true;
        }

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeScalar(mixed $value): mixed
    {
        if (
            $value === null
            || is_string($value)
            || is_int($value)
            || is_float($value)
            || is_bool($value)
        ) {
            return $value;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        return null;
    }
}
