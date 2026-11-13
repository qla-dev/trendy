<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('cnc_machines', function (Blueprint $table) {
            $table->id();
            $table->string('oznaka')->unique();
            $table->string('naziv');
            $table->string('tip')->default('');
            $table->decimal('satnica_programiranja', 10, 2)->default(35);
            $table->decimal('satnica_pripreme', 10, 2)->default(0);
            $table->decimal('satnica_obrade', 10, 2)->default(0);
            $table->boolean('aktivna')->default(true);
            $table->text('napomena')->default('');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('cnc_machines');
    }
};
