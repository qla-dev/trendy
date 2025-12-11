<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('compositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->boolean('alternative')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->string('article_code')->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->text('note')->nullable();
            $table->decimal('quantity', 10, 4)->default(0);
            $table->string('unit')->nullable();
            $table->string('series')->nullable();
            $table->string('normative')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('final')->default(false);
            $table->string('va')->nullable();
            $table->string('primary_class')->nullable();
            $table->string('secondary_class')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('compositions');
    }
};
