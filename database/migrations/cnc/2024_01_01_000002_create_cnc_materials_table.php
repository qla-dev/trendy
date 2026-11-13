<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('cnc_materials', function (Blueprint $table) {
            $table->id();
            $table->string('oznaka')->unique();
            $table->string('naziv');
            $table->string('grupa')->default('');
            $table->string('standard')->default('');
            $table->decimal('gustina', 10, 2)->default(0);
            $table->decimal('cijena_po_kg', 10, 4)->default(0);
            $table->boolean('aktivno')->default(true);
            $table->text('napomena')->default('');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('cnc_materials');
    }
};
