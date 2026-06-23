<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('order_ai_scans', function (Blueprint $table) {
            $table->string('extraction_method', 20)->nullable()->after('document_profile');
            $table->decimal('confidence_score', 6, 4)->nullable()->after('credits_spent');
            $table->unsignedInteger('extraction_duration_ms')->nullable()->after('confidence_score');
            $table->unsignedInteger('ai_duration_ms')->nullable()->after('extraction_duration_ms');
            $table->unsignedInteger('validation_duration_ms')->nullable()->after('ai_duration_ms');
            $table->longText('raw_extracted_text')->nullable()->after('request_prompt');
            $table->json('extraction_payload')->nullable()->after('raw_extracted_text');
            $table->json('validation_warnings')->nullable()->after('extraction_payload');
            $table->json('validation_errors')->nullable()->after('validation_warnings');
            $table->index(['extraction_method', 'created_at'], 'order_ai_scans_method_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('order_ai_scans', function (Blueprint $table) {
            $table->dropIndex('order_ai_scans_method_created_at_index');
            $table->dropColumn([
                'extraction_method',
                'confidence_score',
                'extraction_duration_ms',
                'ai_duration_ms',
                'validation_duration_ms',
                'raw_extracted_text',
                'extraction_payload',
                'validation_warnings',
                'validation_errors',
            ]);
        });
    }
};
