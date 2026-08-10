<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
            $table->id();

            /*
             * Stable catalog identity.
             *
             * The slug must match a supported ApplicationType value before
             * the item can be exposed by the application catalog query.
             */
            $table->string('slug', 64)->unique();

            $table->string('name', 120);
            $table->string('short_description', 255);
            $table->text('description')->nullable();

            /*
             * May contain a Lucide icon key such as "lucide.shield-check"
             * or a public asset path such as "images/applications/app.svg".
             */
            $table->string('icon', 255)->nullable();

            /*
             * Catalog visibility only. This is NOT remote runtime state.
             */
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index([
                'is_published',
                'sort_order',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
