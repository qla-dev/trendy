<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('cnc_positions', function (Blueprint $table) {
            $table->id();
            $table->string('sifra')->index();
            $table->string('naziv')->default('');
            $table->integer('kolicina')->nullable();
            $table->string('tip_komada')->nullable();
            $table->decimal('duzina', 12, 4)->nullable();
            $table->decimal('sirina', 12, 4)->nullable();
            $table->decimal('visina', 12, 4)->nullable();
            $table->decimal('precnik', 12, 4)->nullable();
            $table->decimal('raw_weight_kg', 12, 4)->nullable();
            $table->decimal('output_weight_kg', 12, 4)->nullable();
            $table->string('materijal')->nullable();
            $table->string('masina')->nullable();
            $table->decimal('programiranje', 10, 2)->nullable();
            $table->decimal('priprema', 10, 2)->nullable();
            $table->decimal('obrada', 10, 2)->nullable();
            $table->unsignedBigInteger('povrsinska_zastita_id')->nullable();
            $table->string('povrsinska_zastita')->nullable();
            $table->decimal('povrsinska_zastita_cijena', 10, 4)->nullable();
            $table->unsignedBigInteger('termicka_obrada_id')->nullable();
            $table->string('termicka_obrada')->nullable();
            $table->decimal('termicka_obrada_cijena', 10, 4)->nullable();
            $table->unsignedBigInteger('other_operation_id')->nullable();
            $table->string('other_operation_name')->nullable();
            $table->decimal('other_operation_cost', 10, 4)->nullable();
            $table->string('other_cost1_description')->nullable();
            $table->decimal('other_cost1_amount', 10, 4)->nullable();
            $table->string('other_cost2_description')->nullable();
            $table->decimal('other_cost2_amount', 10, 4)->nullable();
            $table->integer('ensat_count')->nullable();
            $table->decimal('ensat_cost_per_piece', 10, 4)->nullable();
            $table->text('napomena')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('cnc_positions');
    }
};
