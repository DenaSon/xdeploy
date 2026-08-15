<?php

declare(strict_types=1);

use App\Domain\Support\Enums\SupportMessageAuthorRole;
use App\Domain\Support\Enums\SupportRequestCategory;
use App\Domain\Support\Enums\SupportRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_requests', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('server_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('subject', 160);

            $table->enum(
                'category',
                array_column(
                    SupportRequestCategory::cases(),
                    'value',
                ),
            );

            $table->enum(
                'status',
                array_column(
                    SupportRequestStatus::cases(),
                    'value',
                ),
            )->default(
                SupportRequestStatus::Open->value,
            );

            $table->timestamp('last_message_at');
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'status',
                'last_message_at',
            ]);

            $table->index([
                'status',
                'last_message_at',
            ]);

            $table->index('category');
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            $table->foreignId('support_request_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
             * A single author FK plus a persisted role snapshot avoids the
             * ambiguous one-of-two-nullable-FKs shape. The author may become
             * null only if that account is deleted later; historical role and
             * message content remain intact.
             */
            $table->foreignId('author_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum(
                'author_role',
                array_column(
                    SupportMessageAuthorRole::cases(),
                    'value',
                ),
            );

            $table->text('body');

            $table->timestamps();

            $table->index([
                'support_request_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_requests');
    }
};
