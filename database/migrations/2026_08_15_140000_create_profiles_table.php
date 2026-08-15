<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table): void {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->timestamps();
        });

        DB::table('users')
            ->select(['id', 'name'])
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->chunkById(100, function ($users): void {
                $now = now();
                $profiles = [];

                foreach ($users as $user) {
                    $profiles[] = [
                        'user_id' => $user->id,
                        'first_name' => $user->name,
                        'last_name' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($profiles !== []) {
                    DB::table('profiles')->insert($profiles);
                }
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name', 80)->nullable();
        });

        DB::table('profiles')
            ->select(['id', 'user_id', 'first_name', 'last_name'])
            ->chunkById(100, function ($profiles): void {
                foreach ($profiles as $profile) {
                    $fullName = trim(
                        trim((string) $profile->first_name)
                        .' '
                        .trim((string) $profile->last_name),
                    );

                    DB::table('users')
                        ->where('id', $profile->user_id)
                        ->update([
                            'name' => $fullName !== ''
                                ? mb_substr($fullName, 0, 80)
                                : null,
                        ]);
                }
            });

        Schema::dropIfExists('profiles');
    }
};
