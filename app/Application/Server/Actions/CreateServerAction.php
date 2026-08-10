<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\Enums\ServerStatus;
use App\Domain\Server\Services\ServerNameGenerator;
use App\Models\Server;
use App\Models\User;

final readonly class CreateServerAction
{
    public function __construct(
        private ServerNameGenerator $nameGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        User $user,
        array $attributes,
        ServerStatus $status = ServerStatus::Inactive,
    ): Server {
        $attributes['name'] = $this->generateUniqueName(
            $user,
        );

        $server = new Server(
            $attributes,
        );

        $server->status = $status;

        $user
            ->servers()
            ->save($server);

        return $server->refresh();
    }

    private function generateUniqueName(
        User $user,
    ): string {
        do {
            $name = $this->nameGenerator
                ->generate();

            $exists = $user
                ->servers()
                ->where(
                    'name',
                    $name,
                )
                ->exists();
        } while ($exists);

        return $name;
    }
}
