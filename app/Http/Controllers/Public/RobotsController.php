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
        ];

        if (! $seo->index_site) {
            $lines[] = 'Disallow: /';
        } else {
            $lines[] = 'Allow: /';
            $lines[] = 'Disallow: /admin/';
            $lines[] = 'Disallow: /panel/';
            $lines[] = 'Disallow: /login';
            $lines[] = 'Disallow: /verify';
            $lines[] = 'Disallow: /passkeys/';
            $lines[] = 'Disallow: /payments/';
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
