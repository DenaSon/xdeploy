<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PublicEndpoint\PublicEndpointStatusPresentation;
use PHPUnit\Framework\TestCase;

final class PublicEndpointStatusPresentationTest extends TestCase
{
    public function test_actionable_states_have_expected_primary_actions(): void
    {
        $cases = [
            'enabled' => ['آماده استفاده', 'open', 'باز کردن دامنه'],
            'disabled' => ['غیرفعال', 'manage', 'فعال‌سازی دوباره'],
            'pending' => ['نیازمند تکمیل', 'manage', 'ادامه راه‌اندازی'],
            'misconfigured' => ['نیازمند بررسی', 'refresh', 'بررسی دوباره'],
            'unknown' => ['وضعیت نامشخص', 'refresh', 'بررسی وضعیت'],
        ];

        foreach ($cases as $state => [$label, $action, $actionLabel]) {
            $presentation = PublicEndpointStatusPresentation::for($state);

            self::assertSame($label, $presentation['label']);
            self::assertSame($action, $presentation['primary_action']);
            self::assertSame($actionLabel, $presentation['primary_label']);
        }
    }

    public function test_disabling_state_has_no_interactive_primary_action(): void
    {
        $presentation = PublicEndpointStatusPresentation::for('disabling');

        self::assertSame('disabling', $presentation['state']);
        self::assertSame('در حال غیرفعال‌سازی', $presentation['label']);
        self::assertNull($presentation['primary_action']);
        self::assertNull($presentation['primary_label']);
    }

    public function test_legacy_removing_state_uses_safe_disabling_presentation(): void
    {
        $presentation = PublicEndpointStatusPresentation::for('removing');

        self::assertSame('disabling', $presentation['state']);
        self::assertSame('در حال غیرفعال‌سازی', $presentation['label']);
    }

    public function test_unknown_input_falls_back_to_safe_refresh_presentation(): void
    {
        $presentation = PublicEndpointStatusPresentation::for('unexpected-state');

        self::assertSame('unknown', $presentation['state']);
        self::assertSame('refresh', $presentation['primary_action']);
        self::assertSame('بررسی وضعیت', $presentation['primary_label']);
    }
}
