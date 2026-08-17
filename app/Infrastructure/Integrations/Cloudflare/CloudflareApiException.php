<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Cloudflare;

use RuntimeException;

final class CloudflareApiException extends RuntimeException
{
    public const UNAUTHORIZED = 'unauthorized';

    public const FORBIDDEN = 'forbidden';

    public const RATE_LIMITED = 'rate_limited';

    public const INVALID_REQUEST = 'invalid_request';

    public const INVALID_RESPONSE = 'invalid_response';

    public const RESOURCE_LIMIT = 'resource_limit';

    public const MISSING_SCOPES = 'missing_scopes';

    public const REFRESH_FAILED = 'refresh_failed';

    public const REMOTE_ERROR = 'remote_error';

    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }
}
