<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->boolean('alternative')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->string('operation_code')->nullable();
            $table->string('name')->nullable();
            $table->text('note')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('unit_value', 8, 3)->default(0);
            $table->string('normative')->nullable();
            $table->string('va')->nullable();
            $table->string('primary_class')->nullable();
            $table->string('secondary_class')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('operations');
    }
};
