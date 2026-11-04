<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('requires_calibration')->default(true);
            $table->boolean('requires_installation')->default(false);
            $table->unsignedSmallInteger('calibration_interval_months')->default(12);
            $table->unsignedSmallInteger('useful_life_years')->default(5);
            $table->unsignedTinyInteger('residual_value_percent')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_types');
    }
};
