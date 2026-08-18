<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;

final class TelegramBotClient
{
    public function configured(): bool
    {
        return config('services.telegram.enabled') === true
            && $this->validBotToken($this->configString('bot_token'))
            && $this->validBotUsername($this->configString('bot_username'))
            && $this->validWebhookSecret($this->configString('webhook_secret'))
            && $this->validApiBaseUrl($this->configString('api_base_url'))
            && $this->linkTtlSeconds() >= 60
            && $this->linkTtlSeconds() <= 3600
            && $this->connectTimeout() > 0
            && $this->requestTimeout() > 0;
    }

    public function linkTtlSeconds(): int
    {
        return (int) config(
            'services.telegram.link_ttl_seconds',
            600,
        );
    }

    public function deepLink(
        #[SensitiveParameter]
        string $token,
    ): string {
        if (! $this->configured()) {
            throw new TelegramBotException(
                'Telegram integration is not configured.',
            );
        }

        if (
            preg_match(
                '/\A[A-Za-z0-9_-]{43}\z/D',
                $token,
            ) !== 1
        ) {
            throw new TelegramBotException(
                'Telegram link token is invalid.',
            );
        }

        return sprintf(
            'https://t.me/%s?start=%s',
            $this->configString('bot_username'),
            rawurlencode($token),
        );
    }

    public function webhookAuthorized(
        #[SensitiveParameter]
        ?string $providedSecret,
    ): bool {
        if (! $this->configured() || ! is_string($providedSecret)) {
            return false;
        }

        $expectedSecret = $this->configString('webhook_secret');

        return hash_equals(
            $expectedSecret,
            $providedSecret,
        );
    }

    public function setWebhook(string $webhookUrl): void
    {
        if (! $this->configured()) {
            throw new TelegramBotException(
                'Telegram integration is not configured.',
            );
        }

        if (! $this->validWebhookUrl($webhookUrl)) {
            throw new TelegramBotException(
                'Telegram webhook URL is invalid.',
            );
        }

        $url = sprintf(
            '%s/bot%s/setWebhook',
            rtrim($this->configString('api_base_url'), '/'),
            $this->configString('bot_token'),
        );

        try {
            $response = Http::connectTimeout($this->connectTimeout())
                ->timeout($this->requestTimeout())
                ->asJson()
                ->post(
                    $url,
                    [
                        'url' => $webhookUrl,
                        'secret_token' => $this->configString(
                            'webhook_secret',
                        ),
                        'allowed_updates' => ['message'],
                    ],
                );
        } catch (ConnectionException) {
            throw new TelegramBotException(
                'Telegram API connection failed.',
            );
        }

        if (
            ! $response->successful()
            || $response->json('ok') !== true
        ) {
            throw new TelegramBotException(
                'Telegram webhook registration failed.',
            );
        }
    }

    public function sendMessage(
        #[SensitiveParameter]
        string $chatId,
        #[SensitiveParameter]
        string $text,
    ): void {
        if (! $this->configured()) {
            throw new TelegramBotException(
                'Telegram integration is not configured.',
            );
        }

        if (
            preg_match('/\A[1-9][0-9]{0,19}\z/D', $chatId) !== 1
        ) {
            throw new TelegramBotException(
                'Telegram chat is invalid.',
            );
        }

        if (
            trim($text) === ''
            || mb_strlen($text) > 4096
        ) {
            throw new TelegramBotException(
                'Telegram message is invalid.',
            );
        }

        $url = sprintf(
            '%s/bot%s/sendMessage',
            rtrim($this->configString('api_base_url'), '/'),
            $this->configString('bot_token'),
        );

        try {
            $response = Http::connectTimeout($this->connectTimeout())
                ->timeout($this->requestTimeout())
                ->asJson()
                ->post(
                    $url,
                    [
                        'chat_id' => $chatId,
                        'text' => $text,
                    ],
                );
        } catch (ConnectionException) {
            throw new TelegramBotException(
                'Telegram API connection failed.',
            );
        }

        if (
            ! $response->successful()
            || $response->json('ok') !== true
        ) {
            throw new TelegramBotException(
                'Telegram message delivery failed.',
            );
        }
    }

    private function configString(string $key): string
    {
        $value = config("services.telegram.{$key}");

        return is_string($value)
            ? trim($value)
            : '';
    }

    private function connectTimeout(): int
    {
        return (int) config(
            'services.telegram.connect_timeout',
            5,
        );
    }

    private function requestTimeout(): int
    {
        return (int) config(
            'services.telegram.timeout',
            10,
        );
    }

    private function validBotToken(string $token): bool
    {
        return $token !== ''
            && strlen($token) <= 255
            && preg_match('/\s/', $token) !== 1;
    }

    private function validBotUsername(string $username): bool
    {
        return preg_match(
            '/\A[A-Za-z0-9_]{5,32}\z/D',
            $username,
        ) === 1
            && str_ends_with(
                strtolower($username),
                'bot',
            );
    }

    private function validWebhookSecret(string $secret): bool
    {
        $length = strlen($secret);

        return $length >= 16
            && $length <= 256
            && preg_match(
                '/\A[A-Za-z0-9_-]+\z/D',
                $secret,
            ) === 1;
    }

    private function validApiBaseUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && is_string($parts['host'] ?? null)
            && ($parts['host'] ?? '') !== ''
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['query'])
            && ! isset($parts['fragment']);
    }

    private function validWebhookUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && is_string($parts['host'] ?? null)
            && ($parts['host'] ?? '') !== ''
            && ! isset($parts['user'])
            && ! isset($parts['pass'])
            && ! isset($parts['fragment']);
    }
}
