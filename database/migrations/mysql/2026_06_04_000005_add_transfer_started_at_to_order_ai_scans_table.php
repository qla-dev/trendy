<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('order_ai_scans', function (Blueprint $table) {
            $table->timestamp('transfer_started_at')->nullable()->after('processed_at');
            $table->index(['transfer_started_at'], 'order_ai_scans_transfer_started_at_index');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('order_ai_scans', function (Blueprint $table) {
            $table->dropIndex('order_ai_scans_transfer_started_at_index');
            $table->dropColumn('transfer_started_at');
        });
    }
};
