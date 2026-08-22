<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Models\User;
use App\Settings\BrandingSettings;
use Illuminate\Support\Facades\Validator;

final class UpdateBrandingSettings
{
    public function __construct(
        private readonly EnsureCanManageSystemSettings $authorization,
        private readonly PersistSystemSettingsChanges $persist,
        private readonly BrandingSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): void
    {
        $this->authorization->handle($actor);

        $validated = Validator::make($input, [
            'tagline' => ['required', 'string', 'max:120'],
        ])->validate();

        $this->persist->handle(
            actor: $actor,
            settings: $this->settings,
            values: [
                'tagline' => trim($validated['tagline']),
            ],
        );
    }
}
