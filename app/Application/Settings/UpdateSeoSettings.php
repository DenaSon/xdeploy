<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Models\User;
use App\Settings\SeoSettings;
use Illuminate\Support\Facades\Validator;

final class UpdateSeoSettings
{
    public function __construct(
        private readonly EnsureCanManageSystemSettings $authorization,
        private readonly PersistSystemSettingsChanges $persist,
        private readonly SeoSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): void
    {
        $this->authorization->handle($actor);

        $validated = Validator::make($input, [
            'default_title' => ['required', 'string', 'max:70'],
            'default_description' => ['required', 'string', 'max:160'],
            'default_og_image' => ['nullable', 'string', 'max:2048'],
            'index_site' => ['required', 'boolean'],
            'google_site_verification' => ['nullable', 'string', 'max:255'],
            'bing_site_verification' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $ogImage = $this->nullableTrimmedString(
            $validated['default_og_image'] ?? null,
        );
        $googleVerification = $this->nullableTrimmedString(
            $validated['google_site_verification'] ?? null,
        );
        $bingVerification = $this->nullableTrimmedString(
            $validated['bing_site_verification'] ?? null,
        );

        $this->persist->handle(
            actor: $actor,
            settings: $this->settings,
            values: [
                'default_title' => trim($validated['default_title']),
                'default_description' => trim($validated['default_description']),
                'default_og_image' => $ogImage,
                'index_site' => (bool) $validated['index_site'],
                'google_site_verification' => $googleVerification,
                'bing_site_verification' => $bingVerification,
            ],
        );
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
