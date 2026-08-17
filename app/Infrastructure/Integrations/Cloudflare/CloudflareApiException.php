<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Cloudflare;

use RuntimeException;
use Throwable;

final class CloudflareApiException extends RuntimeException
{
    public const UNAUTHORIZED = 'unauthorized';

    public const FORBIDDEN = 'forbidden';

    public const RATE_LIMITED = 'rate_limited';

    public const CONNECTION_FAILED = 'connection_failed';

    public const INVALID_REQUEST = 'invalid_request';

    public const INVALID_RESPONSE = 'invalid_response';

    public const RESOURCE_LIMIT = 'resource_limit';

    public const MISSING_SCOPES = 'missing_scopes';

    public const REFRESH_FAILED = 'refresh_failed';

    public const REMOTE_ERROR = 'remote_error';

    public function __construct(
        public readonly string $reason,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            0,
            $previous,
        );
    }
}
