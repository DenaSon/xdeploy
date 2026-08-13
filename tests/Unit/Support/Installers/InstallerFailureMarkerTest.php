<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Installers;

use App\Support\Installers\InstallerFailureMarker;
use PHPUnit\Framework\TestCase;

final class InstallerFailureMarkerTest extends TestCase
{
    public function test_it_extracts_only_the_structured_installer_failure_marker(): void
    {
        $marker = InstallerFailureMarker::fromOutput(
            "sensitive output\n[xDeploy][n8n][error] stage=image_pull exit_code=124\nmore output",
        );

        self::assertNotNull($marker);
        self::assertSame('n8n', $marker->component);
        self::assertSame('image_pull', $marker->stage);
        self::assertSame(124, $marker->exitCode);
        self::assertSame('n8n_image_pull', $marker->failureCode());
    }

    public function test_it_does_not_treat_arbitrary_sensitive_output_as_a_marker(): void
    {
        self::assertNull(
            InstallerFailureMarker::fromOutput(
                '[xDeploy][n8n][error] stage=image pull exit_code=secret',
            ),
        );
    }
}
