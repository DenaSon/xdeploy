<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\N8n\PublicEndpoint\DTOs;

final readonly class N8nRuntimeConfiguration
{
    public function __construct(
        public ?string $host,
        public ?string $protocol,
        public ?string $webhookUrl,
        public ?string $editorBaseUrl,
        public ?string $proxyHops,
        public ?string $legacyWebhookUrl,
    ) {}

    public function domain(): ?string
    {
        $candidate = $this->host;

        if ($candidate !== null && filter_var($candidate, FILTER_VALIDATE_IP) === false) {
            $candidate = strtolower(trim($candidate));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        foreach ([$this->editorBaseUrl, $this->webhookUrl, $this->legacyWebhookUrl] as $url) {
            $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
            if (is_string($host) && $host !== '') {
                return strtolower($host);
            }
        }

        return null;
    }

    public function hasPublicConfiguration(): bool
    {
        return $this->host !== null
            || $this->protocol !== null
            || $this->webhookUrl !== null
            || $this->editorBaseUrl !== null
            || $this->proxyHops !== null
            || $this->legacyWebhookUrl !== null;
    }

    public function matches(string $domain): bool
    {
        $baseUrl = "https://{$domain}/";

        return strtolower((string) $this->host) === $domain
            && strtolower((string) $this->protocol) === 'https'
            && $this->webhookUrl === $baseUrl
            && $this->editorBaseUrl === $baseUrl
            && $this->proxyHops === '1'
            && $this->legacyWebhookUrl === null;
    }
}
