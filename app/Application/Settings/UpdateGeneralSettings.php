<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Validator;

final class UpdateGeneralSettings
{
    public function __construct(
        private readonly PersistSystemSettingsChanges $persist,
        private readonly GeneralSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): void
    {
        $validated = Validator::make($input, [
            'site_name' => ['required', 'string', 'max:80'],
        ])->validate();

        $this->persist->handle(
            actor: $actor,
            settings: $this->settings,
            values: [
                'site_name' => trim($validated['site_name']),
            ],
        );
    }
}
