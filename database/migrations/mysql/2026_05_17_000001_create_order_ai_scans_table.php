<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('order_ai_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 40)->default('mock');
            $table->string('model', 120)->nullable();
            $table->string('status', 40)->default('uploaded');
            $table->string('processing_step', 160)->nullable();
            $table->unsignedInteger('progress_current')->default(0);
            $table->unsignedInteger('progress_total')->default(100);
            $table->string('source_file_name');
            $table->string('source_file_path');
            $table->string('source_mime_type', 150)->nullable();
            $table->unsignedBigInteger('source_file_size')->nullable();
            $table->string('provider_task_id')->nullable();
            $table->longText('request_prompt')->nullable();
            $table->json('normalized_payload')->nullable();
            $table->json('pantheon_transfer_payload')->nullable();
            $table->json('raw_provider_response')->nullable();
            $table->decimal('credits_spent', 12, 4)->default(0);
            $table->string('pantheon_order_key', 40)->nullable();
            $table->string('pantheon_order_view', 40)->nullable();
            $table->unsignedBigInteger('pantheon_order_qid')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['transferred_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('order_ai_scans');
    }
};
