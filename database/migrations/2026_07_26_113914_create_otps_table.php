<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otps', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            $table->string('phone', 11)->unique();
            $table->string('code', 8);

            $table->timestamp('expires_at');

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
