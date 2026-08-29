<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Analytics\AcquisitionAttribution;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class CaptureAnalyticsAttribution
{
    public function __construct(
        private AcquisitionAttribution $attribution,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->attribution->capture($request);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $next($request);
    }
}
