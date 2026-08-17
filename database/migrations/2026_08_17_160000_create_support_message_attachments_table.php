<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'support_message_attachments',
            function (Blueprint $table): void {
                $table->engine = 'InnoDB';

                $table->id();

                $table->foreignId('support_message_id')
                    ->constrained('support_messages')
                    ->cascadeOnDelete();

                $table->string('disk', 64);
                $table->string('path', 512);
                $table->string('mime_type', 80);
                $table->unsignedBigInteger('size_bytes');
                $table->unsignedInteger('width');
                $table->unsignedInteger('height');
                $table->unsignedTinyInteger('sort_order');

                $table->timestamps();

                $table->unique([
                    'support_message_id',
                    'sort_order',
                ]);

                $table->unique([
                    'disk',
                    'path',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'support_message_attachments',
        );
    }
};
