<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('order_ai_scans', function (Blueprint $table) {
            $table->json('openrouter_payload')->nullable()->after('raw_provider_response');
            $table->json('parser_payload')->nullable()->after('openrouter_payload');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('order_ai_scans', function (Blueprint $table) {
            $table->dropColumn([
                'openrouter_payload',
                'parser_payload',
            ]);
        });
    }
};
