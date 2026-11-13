<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('cnc_quote_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('draft_id');
            $table->unsignedBigInteger('owner_id');
            $table->string('label')->default('Nedovršena ponuda');
            $table->integer('position_count')->default(0);
            $table->bigInteger('created_at_ms')->default(0);
            $table->bigInteger('updated_at_ms')->default(0);
            $table->json('state');
            $table->timestamps();

            $table->unique(['owner_id', 'draft_id']);
            $table->foreign('owner_id')->references('id')->on('cnc_users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('cnc_quote_drafts');
    }
};
