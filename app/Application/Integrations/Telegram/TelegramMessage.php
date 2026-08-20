<?php

declare(strict_types=1);

namespace App\Application\Integrations\Telegram;

use InvalidArgumentException;

final readonly class TelegramMessage
{
    private const int MAX_LENGTH = 4096;

    private function __construct(
        public string $text,
    ) {}

    /**
     * Build a plain-text Telegram message from the same public presentation
     * fields used by database notifications. Internal metadata is ignored.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromNotificationPayload(array $payload): self
    {
        $title = self::requiredText(
            $payload['title'] ?? null,
            'title',
        );
        $message = self::requiredText(
            $payload['message'] ?? null,
            'message',
        );

        $suffix = self::actionSuffix(
            $payload['action_label'] ?? null,
            $payload['action_url'] ?? null,
        );

        $body = $title."\n\n".$message;
        $availableBodyLength = self::MAX_LENGTH - mb_strlen($suffix);

        if ($availableBodyLength < 1) {
            $suffix = '';
            $availableBodyLength = self::MAX_LENGTH;
        }

        if (mb_strlen($body) > $availableBodyLength) {
            $body = mb_substr(
                $body,
                0,
                max(0, $availableBodyLength - 1),
            ).'…';
        }

        return new self($body.$suffix);
    }

    private static function requiredText(
        mixed $value,
        string $field,
    ): string {
        if (! is_string($value)) {
            throw new InvalidArgumentException(
                "Telegram notification {$field} must be a string.",
            );
        }

        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException(
                "Telegram notification {$field} cannot be empty.",
            );
        }

        return $value;
    }

    private static function actionSuffix(
        mixed $label,
        mixed $path,
    ): string {
        if (
            ! is_string($path)
            || ! str_starts_with($path, '/')
        ) {
            return '';
        }

        $baseUrl = config('app.url');

        if (! is_string($baseUrl)) {
            return '';
        }

        $baseUrl = rtrim(trim($baseUrl), '/');

        if (
            $baseUrl === ''
            || filter_var($baseUrl, FILTER_VALIDATE_URL) === false
        ) {
            return '';
        }

        $actionLabel = is_string($label)
            ? trim($label)
            : '';

        $prefix = $actionLabel !== ''
            ? $actionLabel.': '
            : '';

        return "\n\n".$prefix.$baseUrl.$path;
    }
}
