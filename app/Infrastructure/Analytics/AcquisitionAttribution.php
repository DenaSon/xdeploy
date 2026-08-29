<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

use Illuminate\Http\Request;
use Throwable;

final class AcquisitionAttribution
{
    public const FIRST_TOUCH_SESSION_KEY = 'analytics.attribution.first_touch';

    public const LAST_TOUCH_SESSION_KEY = 'analytics.attribution.last_touch';

    /** @var array<string, string> */
    private const PARAMETERS = [
        'source' => 'utm_source',
        'medium' => 'utm_medium',
        'campaign' => 'utm_campaign',
        'content' => 'utm_content',
        'term' => 'utm_term',
    ];

    public function capture(Request $request): void
    {
        try {
            if (! $request->hasSession()) {
                return;
            }

            $touch = $this->touchFromRequest($request);

            if ($touch === []) {
                return;
            }

            $session = $request->session();
            $firstTouch = $session->get(
                self::FIRST_TOUCH_SESSION_KEY,
            );

            if (! is_array($firstTouch) || $firstTouch === []) {
                $session->put(
                    self::FIRST_TOUCH_SESSION_KEY,
                    $touch,
                );
            }

            $session->put(
                self::LAST_TOUCH_SESSION_KEY,
                $touch,
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @return array<string, string> */
    public function firstTouch(): array
    {
        return $this->touchFromSession(
            self::FIRST_TOUCH_SESSION_KEY,
        );
    }

    /** @return array<string, string> */
    public function lastTouch(): array
    {
        return $this->touchFromSession(
            self::LAST_TOUCH_SESSION_KEY,
        );
    }

    /** @return array<string, string> */
    public function eventProperties(): array
    {
        return [
            ...$this->prefixedProperties(
                'first_touch',
                $this->firstTouch(),
            ),
            ...$this->prefixedProperties(
                'last_touch',
                $this->lastTouch(),
            ),
        ];
    }

    /** @return array<string, string> */
    public function firstTouchProperties(): array
    {
        return $this->prefixedProperties(
            'first_touch',
            $this->firstTouch(),
        );
    }

    /** @return array<string, string> */
    public function lastTouchProperties(): array
    {
        return $this->prefixedProperties(
            'last_touch',
            $this->lastTouch(),
        );
    }

    /** @return array<string, string> */
    private function touchFromRequest(Request $request): array
    {
        $touch = [];

        foreach (self::PARAMETERS as $property => $parameter) {
            $value = $this->normalizedValue(
                $request->query($parameter),
            );

            if ($value !== null) {
                $touch[$property] = $value;
            }
        }

        return $touch;
    }

    /** @return array<string, string> */
    private function touchFromSession(string $key): array
    {
        try {
            $request = request();

            if (! $request->hasSession()) {
                return [];
            }

            $stored = $request->session()->get($key, []);
        } catch (Throwable) {
            return [];
        }

        if (! is_array($stored)) {
            return [];
        }

        $touch = [];

        foreach (array_keys(self::PARAMETERS) as $property) {
            $value = $this->normalizedValue(
                $stored[$property] ?? null,
            );

            if ($value !== null) {
                $touch[$property] = $value;
            }
        }

        return $touch;
    }

    /**
     * @param  array<string, string>  $touch
     * @return array<string, string>
     */
    private function prefixedProperties(
        string $prefix,
        array $touch,
    ): array {
        $properties = [];

        foreach ($touch as $property => $value) {
            $properties[$prefix.'_'.$property] = $value;
        }

        return $properties;
    }

    private function normalizedValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $normalized = trim(strip_tags((string) $value));
        $normalized = preg_replace(
            '/[\x00-\x1F\x7F]/u',
            '',
            $normalized,
        );

        if (! is_string($normalized)) {
            return null;
        }

        $normalized = trim($normalized);

        if ($normalized === '') {
            return null;
        }

        return mb_substr($normalized, 0, 160);
    }
}
