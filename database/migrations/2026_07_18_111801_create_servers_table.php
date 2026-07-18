<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::create('servers', function (Blueprint $table) {
            $table->id();

            // Server Information
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(22);

            // Authentication
            $table->string('username');
            $table->string('authentication_type')->default('password');
            $table->text('credential')->nullable();
            $table->string('private_key_path')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
