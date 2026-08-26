<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Settings\SeoSettings;
use Illuminate\Http\Response;

final class RobotsController
{
    public function __invoke(SeoSettings $seo): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin/',
            'Disallow: /panel/',
            'Disallow: /payments/',
        ];

        if (! $seo->index_site) {
            $lines[] = '# Public HTML pages currently emit noindex,nofollow.';
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(
            implode("\n", $lines)."\n",
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }
}
