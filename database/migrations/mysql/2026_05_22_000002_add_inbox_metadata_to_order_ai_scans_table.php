<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->table('order_ai_scans', function (Blueprint $table) {
            $table->string('source_origin', 20)->default('manual')->after('source_file_size');
            $table->string('source_email_subject')->nullable()->after('source_origin');
            $table->string('source_email_from')->nullable()->after('source_email_subject');
            $table->string('source_email_message_id', 191)->nullable()->after('source_email_from');
            $table->string('source_email_uid', 120)->nullable()->after('source_email_message_id');
            $table->timestamp('source_email_received_at')->nullable()->after('source_email_uid');
            $table->unsignedInteger('source_attachment_index')->nullable()->after('source_email_received_at');
            $table->unsignedInteger('source_attachment_total')->nullable()->after('source_attachment_index');

            $table->index(['source_origin', 'source_email_uid'], 'order_ai_scans_origin_uid_index');
            $table->unique(
                ['source_origin', 'source_email_uid', 'source_attachment_index'],
                'order_ai_scans_origin_uid_attachment_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->table('order_ai_scans', function (Blueprint $table) {
            $table->dropUnique('order_ai_scans_origin_uid_attachment_unique');
            $table->dropIndex('order_ai_scans_origin_uid_index');
            $table->dropColumn([
                'source_origin',
                'source_email_subject',
                'source_email_from',
                'source_email_message_id',
                'source_email_uid',
                'source_email_received_at',
                'source_attachment_index',
                'source_attachment_total',
            ]);
        });
    }
};
