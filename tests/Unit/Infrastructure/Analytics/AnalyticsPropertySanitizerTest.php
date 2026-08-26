<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Analytics;

use App\Infrastructure\Analytics\AnalyticsPropertySanitizer;
use PHPUnit\Framework\TestCase;

final class AnalyticsPropertySanitizerTest extends TestCase
{
    public function test_it_removes_sensitive_properties_recursively(): void
    {
        $sanitizer = new AnalyticsPropertySanitizer;

        $result = $sanitizer->sanitize([
            'order_id' => 42,
            'failure_code' => 'provider_timeout',
            'phone' => '09120000000',
            'otp_code' => '123456',
            'credential' => 'secret-value',
            'nested' => [
                'api_token' => 'token-value',
                'region_id' => 'ir-thr-1',
                'code' => 'should-not-leave',
            ],
        ]);

        self::assertSame(42, $result['order_id']);
        self::assertSame(
            'provider_timeout',
            $result['failure_code'],
        );
        self::assertArrayNotHasKey('phone', $result);
        self::assertArrayNotHasKey('otp_code', $result);
        self::assertArrayNotHasKey('credential', $result);
        self::assertSame(
            ['region_id' => 'ir-thr-1'],
            $result['nested'],
        );
    }
}
