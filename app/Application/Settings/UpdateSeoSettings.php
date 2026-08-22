<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Models\User;
use App\Settings\SeoSettings;
use Illuminate\Support\Facades\Validator;

final class UpdateSeoSettings
{
    public function __construct(
        private readonly PersistSystemSettingsChanges $persist,
        private readonly SeoSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): void
    {
        $validated = Validator::make($input, [
            'default_title' => ['required', 'string', 'max:70'],
            'default_description' => ['required', 'string', 'max:160'],
            'default_og_image' => ['nullable', 'string', 'max:2048'],
            'index_site' => ['required', 'boolean'],
        ])->validate();

        $ogImage = $validated['default_og_image'] ?? null;
        $ogImage = is_string($ogImage) ? trim($ogImage) : null;

        $this->persist->handle(
            actor: $actor,
            settings: $this->settings,
            values: [
                'default_title' => trim($validated['default_title']),
                'default_description' => trim($validated['default_description']),
                'default_og_image' => $ogImage === '' ? null : $ogImage,
                'index_site' => (bool) $validated['index_site'],
            ],
        );
    }
}
