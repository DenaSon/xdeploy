<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->boolean('show_in_footer')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->index(
                ['show_in_footer', 'sort_order'],
                'pages_footer_navigation_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropIndex('pages_footer_navigation_index');
            $table->dropColumn([
                'show_in_footer',
                'sort_order',
            ]);
        });
    }
};
