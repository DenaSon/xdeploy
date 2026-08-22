<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Events\SystemSettingsUpdated;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Settings;

final class PersistSystemSettingsChanges
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(
        User $actor,
        Settings $settings,
        array $values,
    ): void {
        $before = $settings->toArray();

        $changedKeys = [];

        foreach ($values as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changedKeys[] = $key;
            }
        }

        if ($changedKeys === []) {
            return;
        }

        DB::transaction(function () use ($settings, $values): void {
            $settings->fill($values)->save();
        });

        event(new SystemSettingsUpdated(
            actorId: (int) $actor->getKey(),
            group: $settings::group(),
            changedKeys: $changedKeys,
        ));
    }
}
