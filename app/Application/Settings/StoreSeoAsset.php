<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class StoreSeoAsset
{
    public function __construct(
        private readonly EnsureCanManageSystemSettings $authorization,
    ) {}

    public function handle(
        User $actor,
        UploadedFile $file,
        string $slot,
        ?string $previousUrl = null,
    ): string {
        $this->authorization->handle($actor);

        $basename = match ($slot) {
            'open-graph' => 'open-graph',
            'organization-logo' => 'organization-logo',
            'favicon' => 'favicon',
            'apple-touch-icon' => 'apple-touch-icon',
            default => throw new RuntimeException('Unsupported SEO asset slot.'),
        };

        $extension = strtolower($file->getClientOriginalExtension());
        $extension = $extension !== '' ? $extension : strtolower((string) $file->extension());

        if ($extension === '') {
            throw new RuntimeException('Unable to determine SEO asset extension.');
        }

        $filename = sprintf(
            '%s-%s.%s',
            $basename,
            Str::lower((string) Str::uuid()),
            $extension,
        );

        $path = $file->storePubliclyAs('seo', $filename, 'public');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Unable to store SEO asset.');
        }

        $this->deletePreviousManagedAsset($previousUrl);

        return Storage::disk('public')->url($path);
    }

    private function deletePreviousManagedAsset(?string $url): void
    {
        $url = trim((string) $url);

        if ($url === '') {
            return;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/storage/seo/')) {
            return;
        }

        Storage::disk('public')->delete(ltrim(substr($path, strlen('/storage/')), '/'));
    }
}
