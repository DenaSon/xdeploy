<?php
namespace App\Domain\Server\Rules;

class ServerRules
{
    public static function store(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'host' => ['required', 'string', 'max:255'],

            'port' => ['required', 'integer', 'between:1,65535'],

            'username' => ['required', 'string', 'max:255'],

            'authentication_type' => [
                'required',
                'in:password,private_key',
            ],

            'credential' => [
                'nullable',
                'string',
            ],

            'private_key_path' => [
                'nullable',
                'string',
                'max:500',
            ],

            'is_active' => [
                'boolean',
            ],
        ];
    }
}
