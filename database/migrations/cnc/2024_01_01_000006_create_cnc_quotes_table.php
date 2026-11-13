<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql';

    public function up(): void
    {
        Schema::connection('mysql')->create('cnc_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('broj_ponude');
            $table->string('broj_upita_kupca')->nullable();
            $table->date('datum')->nullable();
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected'])->default('draft');
            $table->unsignedBigInteger('kupac_id')->nullable();
            $table->string('kupac_naziv')->default('');
            $table->decimal('margin_percent', 8, 4)->default(0);
            $table->boolean('transport_included')->default(true);
            $table->boolean('staffelpreise_aktivno')->default(false);
            $table->enum('staffel_nacin', ['shared', 'per-position'])->nullable();
            $table->json('staffel_kolicine')->nullable();
            $table->decimal('total_cost', 14, 4)->default(0);
            $table->decimal('total_selling_price', 14, 4)->default(0);
            $table->string('created_by')->default('');
            $table->timestamp('vrijeme_izrade_pocetak')->nullable();
            $table->timestamp('vrijeme_izrade_kraj')->nullable();
            $table->unsignedBigInteger('vrijeme_izrade_ms')->nullable();
            $table->timestamps();

            $table->index('kupac_id');
            $table->index('status');
        });

        Schema::connection('mysql')->create('cnc_quote_positions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quote_id');
            $table->string('position_number')->default('');
            $table->string('code')->default('');
            $table->string('name')->default('');
            $table->integer('quantity')->default(1);
            $table->string('shape')->default('');
            $table->decimal('length', 12, 4)->default(0);
            $table->decimal('width', 12, 4)->default(0);
            $table->decimal('height', 12, 4)->default(0);
            $table->decimal('diameter', 12, 4)->default(0);
            $table->string('material')->default('');
            $table->decimal('programming_minutes', 10, 2)->default(0);
            $table->decimal('setup_minutes', 10, 2)->default(0);
            $table->decimal('machining_minutes', 10, 2)->default(0);
            $table->string('machine')->default('');
            $table->unsignedBigInteger('surface_protection_id')->nullable();
            $table->string('surface_protection_name')->nullable();
            $table->json('surface_protection')->nullable();
            $table->unsignedBigInteger('heat_treatment_id')->nullable();
            $table->string('heat_treatment_name')->nullable();
            $table->json('heat_treatment')->nullable();
            $table->unsignedBigInteger('other_operation_id')->nullable();
            $table->string('other_operation_name')->nullable();
            $table->json('other_operation')->nullable();
            $table->string('other_cost1_description')->nullable();
            $table->decimal('other_cost1_amount', 10, 4)->nullable();
            $table->string('other_cost2_description')->nullable();
            $table->decimal('other_cost2_amount', 10, 4)->nullable();
            $table->integer('ensat_count')->nullable();
            $table->decimal('ensat_price_per_piece', 10, 4)->nullable();
            $table->decimal('weight_kg', 12, 4)->default(0);
            $table->decimal('output_weight_kg', 12, 4)->nullable();
            $table->decimal('output_weight_used_kg', 12, 4)->nullable();
            $table->decimal('material_cost', 14, 4)->default(0);
            $table->decimal('programming_cost', 14, 4)->default(0);
            $table->decimal('setup_cost', 14, 4)->default(0);
            $table->decimal('machining_cost', 14, 4)->default(0);
            $table->decimal('surface_protection_cost', 14, 4)->default(0);
            $table->decimal('heat_treatment_cost', 14, 4)->default(0);
            $table->decimal('other_operation_cost', 14, 4)->nullable();
            $table->decimal('other_costs_total', 14, 4)->nullable();
            $table->decimal('ensat_cost_per_piece', 10, 4)->nullable();
            $table->decimal('total_cost_per_piece', 14, 4)->default(0);
            $table->decimal('total_cost', 14, 4)->default(0);
            $table->decimal('machine_rate', 10, 2)->default(0);
            $table->decimal('margin_percent', 8, 4)->default(0);
            $table->decimal('margin_amount', 14, 4)->default(0);
            $table->decimal('margin_value_per_piece', 14, 4)->default(0);
            $table->decimal('selling_price_per_piece', 14, 4)->default(0);
            $table->decimal('total_selling_price', 14, 4)->default(0);
            $table->json('staffelpreise')->nullable();
            $table->json('staffel_kolicine')->nullable();
            $table->timestamps();

            $table->foreign('quote_id')->references('id')->on('cnc_quotes')->onDelete('cascade');
        });

        Schema::connection('mysql')->create('cnc_quote_contact_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quote_id');
            $table->date('datum');
            $table->enum('kanal', ['email', 'telefon', 'sastanak', 'drugo'])->default('email');
            $table->string('kontakt_osoba')->default('');
            $table->text('ishod')->default('');
            $table->text('napomena')->default('');
            $table->string('evidentirao')->default('');
            $table->timestamp('evidentirano_u')->nullable();
            $table->timestamps();

            $table->foreign('quote_id')->references('id')->on('cnc_quotes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('cnc_quote_contact_history');
        Schema::connection('mysql')->dropIfExists('cnc_quote_positions');
        Schema::connection('mysql')->dropIfExists('cnc_quotes');
    }
};
