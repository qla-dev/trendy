<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('cnc_surface_protections', function (Blueprint $table) {
            $table->id();
            $table->string('naziv');
            $table->decimal('cijena', 10, 4)->default(0);
            $table->enum('tip_cijene', ['komad', 'kg', 'fiksno'])->default('komad');
            $table->boolean('aktivno')->default(true);
            $table->text('napomena')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql')->create('cnc_heat_treatments', function (Blueprint $table) {
            $table->id();
            $table->string('naziv');
            $table->decimal('cijena', 10, 4)->default(0);
            $table->enum('tip_cijene', ['komad', 'kg', 'fiksno'])->default('komad');
            $table->boolean('aktivno')->default(true);
            $table->text('napomena')->nullable();
            $table->timestamps();
        });

        Schema::connection('mysql')->create('cnc_other_operations', function (Blueprint $table) {
            $table->id();
            $table->string('naziv');
            $table->decimal('cijena', 10, 4)->default(0);
            $table->enum('tip_cijene', ['komad', 'kg', 'fiksno'])->default('komad');
            $table->boolean('aktivno')->default(true);
            $table->text('napomena')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('cnc_other_operations');
        Schema::connection('mysql')->dropIfExists('cnc_heat_treatments');
        Schema::connection('mysql')->dropIfExists('cnc_surface_protections');
    }
};
