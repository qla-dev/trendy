<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('cnc_customers', function (Blueprint $table) {
            $table->id();
            $table->string('naziv');
            $table->string('adresa')->nullable();
            $table->string('postanski_broj')->nullable();
            $table->string('grad')->nullable();
            $table->string('drzava')->nullable();
            $table->string('kontakt_osoba')->nullable();
            $table->string('email')->nullable();
            $table->string('telefon')->nullable();
            $table->boolean('aktivan')->default(true);
            $table->text('napomena')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('cnc_customers');
    }
};
