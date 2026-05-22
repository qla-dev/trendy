<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('order_ai_scans', function (Blueprint $table) {
            $table->unsignedInteger('page_count')->nullable()->after('source_file_size');
            $table->unsignedInteger('billed_tokens')->nullable()->after('page_count');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('order_ai_scans', function (Blueprint $table) {
            $table->dropColumn(['page_count', 'billed_tokens']);
        });
    }
};
