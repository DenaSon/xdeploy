<?php

namespace App\Livewire\Concerns;

use App\Models\Server;

trait HasServerForm
{
    public string $name = '';

    public string $host = '';

    public int $port = 22;

    public string $username = '';

    public string $credential = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'host' => ['required', 'string'],

            'port' => ['required', 'integer', 'between:1,65535'],

            'username' => ['required', 'string'],

            'credential' => ['required', 'string'],
        ];
    }

    protected function serverAlreadyExists(?Server $ignore = null): bool
    {
        return Server::query()
            ->where('user_id', auth()->id())
            ->where('host', $this->host)
            ->where('port', $this->port)
            ->when(
                $ignore,
                fn ($query) => $query->whereKeyNot($ignore)
            )
            ->exists();
    }

    protected function fillServerForm(array $data): void
    {
        $this->fill([
            'name' => $data['name'],
            'host' => $data['host'],
            'port' => $data['port'],
            'username' => $data['username'],
            'credential' => $data['credential'],
        ]);
    }
}
