<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('cnc_users', function (Blueprint $table) {
            $table->id();
            $table->string('ime_prezime');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'kalkulant'])->default('kalkulant');
            $table->boolean('aktivan')->default(true);
            $table->json('permissions')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('mysql')->create('cnc_personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cnc_user_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('cnc_user_id')->references('id')->on('cnc_users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('cnc_personal_access_tokens');
        Schema::connection('mysql')->dropIfExists('cnc_users');
    }
};
