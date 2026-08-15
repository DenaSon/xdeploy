<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();

            $table->string('title', 160);

            $table->string('slug', 160)
                ->unique();

            $table->longText('content')
                ->nullable();

            $table->boolean('is_published')
                ->default(false);

            $table->timestamp('published_at')
                ->nullable();

            $table->boolean('show_in_footer')
                ->default(false);

            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->timestamps();

            $table->index([
                'is_published',
                'published_at',
            ]);

            $table->index(
                [
                    'show_in_footer',
                    'sort_order',
                ],
                'pages_footer_navigation_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
