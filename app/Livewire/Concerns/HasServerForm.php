<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\Server;

trait HasServerForm
{
    public string $name = '';

    public string $host = '';

    public int $port = 22;

    public string $username = '';

    public string $credential = '';

    /**
     * @return array<string, array<int, string>>
     */
    protected function serverRules(
        bool $requireCredential = true,
    ): array {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'host' => [
                'required',
                'string',
                'max:255',
            ],

            'port' => [
                'required',
                'integer',
                'between:1,65535',
            ],

            'username' => [
                'required',
                'string',
                'max:255',
            ],

            'credential' => [
                $requireCredential
                    ? 'required'
                    : 'nullable',
                'string',
            ],
        ];
    }

    protected function serverAlreadyExists(
        ?Server $ignore = null,
    ): bool {
        return Server::query()
            ->where(
                'user_id',
                auth()->id(),
            )
            ->where(
                'host',
                $this->host,
            )
            ->where(
                'port',
                $this->port,
            )
            ->when(
                $ignore !== null,
                static fn ($query) => $query->whereKeyNot(
                    $ignore->getKey(),
                ),
            )
            ->exists();
    }

    /**
     * Fill only non-sensitive server fields.
     *
     * @param array{
     *     name: string,
     *     host: string,
     *     port: int,
     *     username: string
     * } $data
     */
    protected function fillServerForm(
        array $data,
    ): void {
        $this->fill([
            'name' => $data['name'],
            'host' => $data['host'],
            'port' => $data['port'],
            'username' => $data['username'],
        ]);

        /*
         * Never expose the existing decrypted credential
         * through Livewire public state.
         */
        $this->credential = '';
    }
}
