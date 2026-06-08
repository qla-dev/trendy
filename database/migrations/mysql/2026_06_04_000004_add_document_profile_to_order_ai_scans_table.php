<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('order_ai_scans', function (Blueprint $table) {
            $table->string('document_profile', 40)->nullable()->after('model');
            $table->index(['document_profile', 'created_at'], 'order_ai_scans_profile_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('order_ai_scans', function (Blueprint $table) {
            $table->dropIndex('order_ai_scans_profile_created_at_index');
            $table->dropColumn('document_profile');
        });
    }
};
