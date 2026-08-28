<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domain\Server\Enums\AuthenticationType;
use App\Models\Server;
use App\Rules\SSHPrivateKey;
use Illuminate\Validation\Rule;

trait HasServerForm
{
    public string $host = '';

    public int $port = 22;

    public string $username = '';

    public string $credential = '';

    public string $authenticationType = AuthenticationType::Password->value;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function serverRules(
        bool $requireCredential = true,
    ): array {
        $credentialRules = [
            $requireCredential
                ? 'required'
                : 'nullable',
            'string',
        ];

        if (
            $this->authenticationType
            === AuthenticationType::SSHKey->value
        ) {
            $credentialRules[] = new SSHPrivateKey;
        }

        return [
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

            'credential' => $credentialRules,

            'authenticationType' => [
                'required',
                Rule::in(
                    array_map(
                        static fn (AuthenticationType $type): string => $type->value,
                        AuthenticationType::supportedCases(),
                    ),
                ),
            ],
        ];
    }

    /**
     * Convert Livewire-facing state to the canonical Server attribute shape.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeServerFormData(array $data): array
    {
        $data['authentication_type'] = $data['authenticationType'];

        unset(
            $data['authenticationType'],
        );

        return $data;
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
     * Fill only non-sensitive server connection fields.
     *
     * @param array{
     *     host: string,
     *     port: int,
     *     username: string,
     *     authentication_type?: AuthenticationType|string,
     * } $data
     */
    protected function fillServerForm(
        array $data,
    ): void {
        $this->fill([
            'host' => $data['host'],
            'port' => $data['port'],
            'username' => $data['username'],
        ]);

        $authenticationType = $data['authentication_type']
            ?? AuthenticationType::Password;

        $this->authenticationType = $authenticationType instanceof AuthenticationType
            ? $authenticationType->value
            : AuthenticationType::from($authenticationType)->value;

        /*
         * Never expose the existing decrypted credential
         * through Livewire public state.
         */
        $this->credential = '';
    }
}
