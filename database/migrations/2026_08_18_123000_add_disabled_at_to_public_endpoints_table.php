<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_endpoints', function (Blueprint $table): void {
            $table->timestamp('disabled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('public_endpoints', function (Blueprint $table): void {
            $table->dropColumn('disabled_at');
        });
    }
};
