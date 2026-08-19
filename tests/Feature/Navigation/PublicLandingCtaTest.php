<?php

declare(strict_types=1);

namespace Tests\Feature\Navigation;

use Tests\TestCase;

final class PublicLandingCtaTest extends TestCase
{
    public function test_landing_primary_cta_is_a_direct_link_to_login(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('از سرور تا سرویس')
            ->assertSee('مشاهده مسیر راه‌اندازی');

        $content = (string) $response->getContent();
        $loginUrl = preg_quote(route('login'), '/');

        self::assertMatchesRegularExpression(
            '/<a\s+[^>]*href="'.$loginUrl.'"[^>]*>.*?شروع استفاده.*?<\/a>/s',
            $content,
        );
    }
}
