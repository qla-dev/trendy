<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('cnc_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->default('');
            $table->string('actor_email')->default('');
            $table->string('action', 50);
            $table->string('entity_type', 50);
            $table->string('entity_id')->default('');
            $table->string('entity_label')->default('');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('entity_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('cnc_activity_logs');
    }
};
