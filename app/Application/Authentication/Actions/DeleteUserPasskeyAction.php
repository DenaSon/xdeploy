<?php

declare(strict_types=1);

namespace App\Application\Authentication\Actions;

use App\Domain\Authentication\Exceptions\CannotDeleteLastAdminPasskeyException;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Passkey;

final readonly class DeleteUserPasskeyAction
{
    public function __construct(
        private DeletePasskey $deletePasskey,
    ) {}

    public function handle(
        User $user,
        int $passkeyId,
    ): void {
        DB::transaction(function () use ($user, $passkeyId): void {
            $passkeys = $user->passkeys()
                ->lockForUpdate()
                ->get();

            /** @var Passkey|null $passkey */
            $passkey = $passkeys->firstWhere('id', $passkeyId);

            if (! $passkey instanceof Passkey) {
                throw (new ModelNotFoundException)
                    ->setModel(Passkey::class, [$passkeyId]);
            }

            if ($user->isAdmin() && $passkeys->count() <= 1) {
                throw new CannotDeleteLastAdminPasskeyException;
            }

            ($this->deletePasskey)(
                $user,
                $passkey,
            );
        });
    }
}
