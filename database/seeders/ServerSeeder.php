<?php

namespace Database\Seeders;

use App\Domain\Server\Enums\AuthenticationType;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServerSeeder extends Seeder
{
    public function run(): void
    {
        Server::updateOrCreate(
            ['host' => '127.0.0.1'],
            [
                'name' => 'Local Docker SSH',
                'user_id' => User::first()->id,
                'host' => '127.0.0.1',
                'port' => 2222,

                'username' => 'xdeploy',
                'credential' => 'xdeploy',

                'authentication_type' => AuthenticationType::Password,

                'status' => 'active',
            ]
        );
    }
}
