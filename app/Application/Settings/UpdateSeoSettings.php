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
            'site_alternate_name' => ['required', 'string', 'max:80'],
            'organization_logo' => ['nullable', 'string', 'max:2048'],
            'favicon' => ['nullable', 'string', 'max:2048'],
            'apple_touch_icon' => ['nullable', 'string', 'max:2048'],
            'index_site' => ['required', 'boolean'],
            'google_site_verification' => ['nullable', 'string', 'max:255'],
            'bing_site_verification' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $this->persist->handle(
            actor: $actor,
            settings: $this->settings,
            values: [
                'default_title' => trim($validated['default_title']),
                'default_description' => trim($validated['default_description']),
                'default_og_image' => $this->nullableTrimmedString(
                    $validated['default_og_image'] ?? null,
                ),
                'site_alternate_name' => trim($validated['site_alternate_name']),
                'organization_logo' => $this->nullableTrimmedString(
                    $validated['organization_logo'] ?? null,
                ),
                'favicon' => $this->nullableTrimmedString(
                    $validated['favicon'] ?? null,
                ),
                'apple_touch_icon' => $this->nullableTrimmedString(
                    $validated['apple_touch_icon'] ?? null,
                ),
                'index_site' => (bool) $validated['index_site'],
                'google_site_verification' => $this->nullableTrimmedString(
                    $validated['google_site_verification'] ?? null,
                ),
                'bing_site_verification' => $this->nullableTrimmedString(
                    $validated['bing_site_verification'] ?? null,
                ),
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
